<?php

namespace App\Services;

use App\Exceptions\DuplicateLedgerUploadException;
use App\Models\GeneralLedger;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Dynamic importer for the MDS 101 general ledger.
 *
 * Nothing about rows or columns is hardcoded. 
 *   1. detectHeaderRow()  finds the header band by its anchor labels.
 *   2. buildColumnMap()   maps columns to canonical fields via config('ledger'),
 *                         and discovers the deduction/tax block by boundary
 *                         anchors so a tax-law change needs no code change.
 *   3. parseRows()        segments months by divider rows and classifies each row.
 *
 * Every import is a snapshot. The accountant keeps one workbook and
 * re-imports it as months are appended. Each import stores a complete snapshot
 * under a new upload and marks it current, so re-importing the same growing
 * file cannot duplicate earlier months, and a payment mode that was blank
 * before simply appears filled in the new snapshot.
 *
 * Run from a queued job: reading cached formula values is CPU-bound.
 */
class LedgerImportService
{
    protected array $cfg;

    public function __construct()
    {
        $this->cfg = config('ledger');
    }

    /**
     * @param  bool  $force  // force re-import
     *
     * @throws DuplicateLedgerUploadException
     */
    public function import(
        string $absolutePath,
        int $year,
        string $originalName,
        ?int $uploadedBy = null,
        bool $force = false
    ): Upload {
        // 1. Validation: identical bytes. Runs BEFORE anything is written, so a
        // rejected duplicate never creates an uploads row or burns a snapshot.
        $fileHash = hash_file('sha256', $absolutePath);

        if (! $force && $prior = $this->findDuplicate($year, 'file_hash', $fileHash)) {
            throw new DuplicateLedgerUploadException($prior, 'file');
        }

        $upload = Upload::create([
            'original_name' => $originalName,
            'stored_path'   => $absolutePath,
            'sheet_name'    => $this->cfg['sheet'],
            'fiscal_year'   => $year,
            'status'        => 'parsing',
            'is_current'    => false,
            'file_hash'     => $fileHash,
            'uploaded_by'   => $uploadedBy,
        ]);

        try {
            $sheet   = $this->loadSheet($absolutePath);
            $mainRow = $this->detectHeaderRow($sheet);
            $map     = $this->buildColumnMap($sheet, $mainRow);
            $legend  = $this->discoverLegend($sheet, $mainRow);
            $records = $this->parseRows($sheet, $mainRow, $map, $year, $legend);

            // Validation 2: identical data. a workbook can be byte-different
            // yet carry exactly the same ledger. Fingerprint the parsed rows.
            $contentHash = $this->contentHash($records);

            if (! $force && $prior = $this->findDuplicate($year, 'content_hash', $contentHash, $upload->id)) {
                $upload->delete();
                throw new DuplicateLedgerUploadException($prior, 'content');
            }

            $transactions = 0;
            foreach ($records as $rec) {
                if ($rec['row_type'] === 'transaction') {
                    $transactions++;
                }
            }

            DB::transaction(function () use ($records, $upload, $map, $transactions, $contentHash) {
                foreach (array_chunk($records, 500) as $chunk) {
                    GeneralLedger::insert(array_map(
                        fn ($rec) => $this->toRow($rec, $upload->id),
                        $chunk
                    ));
                }

                // this snapshot becomes the live ledger
                Upload::where('fiscal_year', $upload->fiscal_year)
                    ->where('id', '!=', $upload->id)
                    ->update(['is_current' => false]);

                $upload->update([
                    'status'            => 'done',
                    'is_current'        => true,
                    'content_hash'      => $contentHash,
                    'row_count'         => count($records),
                    'transaction_count' => $transactions,
                    'header_map'        => $this->describeMap($map),
                ]);
            });

            $this->applyRetention($upload);
        } catch (DuplicateLedgerUploadException $e) {
            throw $e;   // the upload row is already removed; nothing to mark
        } catch (\Throwable $e) {
            $upload->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
            throw $e;
        }

        return $upload->fresh();
    }

    /** An already-completed import of the same workbook, if one exists. */
    protected function findDuplicate(int $year, string $column, string $hash, ?int $excludeId = null): ?Upload
    {
        return Upload::where('fiscal_year', $year)
            ->where('status', 'done')
            ->where($column, $hash)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('id')
            ->first();
    }

    /**
     * Fingerprint the parsed ledger, ignoring everything outside the data:
     * file metadata, styling, and recalculated formula chains.
     *
     * Deliberately excludes raw_row (carries incidental cell noise) so a
     * cosmetic re-save is recognised as unchanged data.
     */
    protected function contentHash(array $records): string
    {
        $ctx = hash_init('sha256');

        foreach ($records as $rec) {
            $slim = [
                'r' => $rec['source_row'],
                't' => $rec['row_type'],
                'y' => $rec['ledger_year'],
                'm' => $rec['ledger_month'],
                'x' => $rec['tax_details'],
            ];
            foreach ($this->cfg['fields'] as $field) {
                $slim[$field] = $rec[$field] ?? null;
            }
            ksort($slim);
            hash_update($ctx, json_encode($slim) . "\n");
        }

        return hash_final($ctx);
    }

    /**
     * Tiered retention.
     *
     * The uploads row is never deleted: it is the audit trail (who imported
     * what, when, with which fingerprint) and costs about a kilobyte. What gets
     * reclaimed is the heavy material behind it:
     *
     *   - general_ledgers rows for snapshots older than keep_row_snapshots
     *   - the stored workbook for snapshots older than keep_files
     *
     * The current workbook is retained because export uses it as the template
     * that preserves the government format.
     */
    protected function applyRetention(Upload $current): void
    {
        $completed = Upload::where('fiscal_year', $current->fiscal_year)
            ->where('status', 'done')
            ->orderByDesc('id')
            ->get();

        $keepRows  = (int) ($this->cfg['keep_row_snapshots'] ?? 2);
        $keepFiles = (int) ($this->cfg['keep_files'] ?? 1);

        foreach ($completed as $i => $upload) {
            // rank 0 is the newest (the snapshot just imported)
            if ($keepRows > 0 && $i >= $keepRows && !$upload->rows_pruned_at) {
                GeneralLedger::where('upload_id', $upload->id)->delete();
                $upload->forceFill(['rows_pruned_at' => now()])->save();
            }

            if ($i >= $keepFiles && !$upload->file_deleted_at) {
                $this->deleteStoredFile($upload);
            }
        }
    }

    /** Remove the workbook from disk, tolerating an already-missing file. */
    protected function deleteStoredFile(Upload $upload): void
    {
        $path = $upload->stored_path;

        if ($path && is_file($path)) {
            @unlink($path);
        }

        $upload->forceFill(['file_deleted_at' => now()])->save();
    }

    // loading

    protected function loadSheet(string $path): Worksheet
    {
        $reader = IOFactory::createReaderForFile($path);
        // NOTE: do NOT setReadEmptyCells(false). Status-coloured cells often carry
        // a fill but no value; skipping empty cells drops them before their fill
        // can be read, which makes every status_flag come back null.
        $spreadsheet = $reader->load($path);

        return $spreadsheet->getSheetByName($this->cfg['sheet'])
            ?? $spreadsheet->getActiveSheet();
    }

    /** Cached value of a cell (formula result if it is a formula). */
    protected function cellVal(Worksheet $sheet, int $row, int $col): mixed
    {
        $coord = Coordinate::stringFromColumnIndex($col) . $row;
        if (! $sheet->cellExists($coord)) {
            return null;
        }
        $cell = $sheet->getCell($coord);
        if ($cell->isFormula()) {
            $cached = $cell->getOldCalculatedValue();
            return $cached === '' ? null : $cached;
        }
        $v = $cell->getValue();
        return $v === '' ? null : $v;
    }

    // header band

    protected function norm(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        return trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $v)));
    }

    protected function highestColumn(Worksheet $sheet): int
    {
        return Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
    }

    protected function detectHeaderRow(Worksheet $sheet): int
    {
        $anchors = $this->cfg['anchors'];
        $cols    = $this->highestColumn($sheet);

        for ($r = 1; $r <= $this->cfg['header_scan_max']; $r++) {
            $labels = [];
            for ($c = 1; $c <= $cols; $c++) {
                $labels[$this->norm($this->cellVal($sheet, $r, $c))] = true;
            }
            $hit = true;
            foreach ($anchors as $a) {
                if (! isset($labels[$a])) {
                    $hit = false;
                    break;
                }
            }
            if ($hit) {
                return $r;
            }
        }

        throw new \RuntimeException('Ledger header row not found');
    }

    /** coordinate -> top-left value, so merged header cells resolve. */
    protected function mergeLookup(Worksheet $sheet): array
    {
        $look = [];
        foreach ($sheet->getMergeCells() as $range) {
            [$start] = explode(':', $range);
            $topLeft = $this->cellVal(
                $sheet,
                (int) preg_replace('/\D/', '', $start),
                Coordinate::columnIndexFromString(preg_replace('/\d/', '', $start))
            );
            foreach (Coordinate::extractAllCellReferencesInRange($range) as $coord) {
                $look[$coord] = $topLeft;
            }
        }
        return $look;
    }

    protected function bandValue(Worksheet $sheet, int $row, int $col, array $merges): mixed
    {
        $direct = $this->cellVal($sheet, $row, $col);
        if ($direct !== null && $direct !== '') {
            return $direct;
        }
        $coord = Coordinate::stringFromColumnIndex($col) . $row;
        return $merges[$coord] ?? null;
    }

    /**
     * canonical field -> 1-based column index, purely from header labels.
     * The '__tax__' key holds the dynamically discovered deduction block
     * as a list of [label, columnIndex].
     */
    protected function buildColumnMap(Worksheet $sheet, int $mainRow): array
    {
        $merges = $this->mergeLookup($sheet);
        $cols   = $this->highestColumn($sheet);
        $map    = [];
        $dvCols = [];

        for ($c = 1; $c <= $cols; $c++) {
            $sup  = $this->bandValue($sheet, $mainRow - 1, $c, $merges);
            $main = $this->bandValue($sheet, $mainRow, $c, $merges);
            $sub  = $this->bandValue($sheet, $mainRow + 1, $c, $merges);

            $field = $this->canon($sup, $main, $sub);
            if ($field === null) {
                continue;
            }
            if ($field === 'dv') {
                $dvCols[] = $c;
                continue;
            }
            $map[$field] ??= $c;
        }

        // OBR # is split: labelled column = prefix, the next column = number
        if (isset($map['obr_prefix'])) {
            $map['obr_no'] = $map['obr_prefix'] + 1;
        }
        // DV# spans two merged columns: first = prefix, second = number
        if ($dvCols) {
            $map['dv_prefix'] = $dvCols[0];
            if (isset($dvCols[1])) {
                $map['dv_no'] = $dvCols[1];
            }
        }

        $map['__tax__'] = $this->discoverTaxBlock($sheet, $mainRow, $merges, $map, $cols);

        return $map;
    }

    /**
     * Discover the deduction/tax block by boundary anchors.
     *
     * Everything between the column after 'start_after' and the column whose
     * main label starts an 'end_labels' entry is a deduction input. Labels come
     * from the sheet itself, so adding, renaming, or removing a tax column
     * between fiscal years requires no code change and no migration.
     */
    protected function discoverTaxBlock(Worksheet $sheet, int $mainRow, array $merges, array $map, int $cols): array
    {
        $tb = $this->cfg['tax_block'];
        $anchor = $map[$tb['start_after']] ?? null;
        if (! $anchor) {
            return [];
        }

        $start = $anchor + 1;
        $end   = null;
        for ($c = $start; $c <= $cols; $c++) {
            $main = $this->norm($this->bandValue($sheet, $mainRow, $c, $merges));
            foreach ($tb['end_labels'] as $lbl) {
                if ($main !== '' && str_starts_with($main, $lbl)) {
                    $end = $c;
                    break 2;
                }
            } 
        }
        if (! $end) {
            return [];
        }

        $block = [];
        for ($c = $start; $c < $end; $c++) {
            $main = $this->norm($this->bandValue($sheet, $mainRow, $c, $merges));
            $sub  = $this->norm($this->bandValue($sheet, $mainRow + 1, $c, $merges));

            if (! empty($tb['join_continuation']) && str_starts_with($sub, '/') && $main !== '') {
                $label = $main . $sub;                       // "refund of overpayment/liquidated damages"
            } elseif ($sub !== '' && ! in_array($sub, $tb['group_labels'], true)) {
                $label = $sub;
            } else {
                $label = $main;
            }

            $label = trim($label);
            if ($label === '' || in_array($label, $tb['skip_labels'], true)) {
                continue;
            }
            $block[] = [$label, $c];
        }

        return $block;
    }

    // ------------------------------------------------------------ status legend

    /**
     * Discover the colour legend without hardcoding its position.
     *
     * The legend is the longest contiguous vertical run of (filled cell + text
     * label immediately to its right) strictly above the header row. That rule
     * finds column W on the CY2026 sheet and correctly ignores the similarly
     * filled PROCESSING TIME block, which only runs three rows.
     *
     * @return array<string,string>  ARGB => label
     */
    protected function discoverLegend(Worksheet $sheet, int $mainRow): array
    {
        $cfg  = $this->cfg['legend'];
        $cols = $this->highestColumn($sheet);
        $best = [];

        for ($c = 1; $c <= $cols; $c++) {
            $run = [];
            for ($r = 1; $r < $mainRow; $r++) {
                $argb  = $this->fillArgb($sheet, $r, $c);
                $label = $this->cellVal($sheet, $r, $c + 1);

                if ($argb !== null && is_string($label) && trim($label) !== '') {
                    $run[$argb] = trim($label);
                } else {
                    if (count($run) > count($best)) {
                        $best = $run;
                    }
                    $run = [];
                }
            }
            if (count($run) > count($best)) {
                $best = $run;
            }
        }

        return count($best) >= ($cfg['min_entries'] ?? 2) ? $best : [];
    }

    /** A cell's solid fill colour as ARGB, or null when effectively unfilled. */
    protected function fillArgb(Worksheet $sheet, int $row, int $col): ?string
    {
        $coord = Coordinate::stringFromColumnIndex($col) . $row;
        if (! $sheet->cellExists($coord)) {
            return null;
        }

        $fill = $sheet->getStyle($coord)->getFill();
        if ($fill->getFillType() === null || $fill->getFillType() === Fill::FILL_NONE) {
            return null;
        }

        $argb = $fill->getStartColor()->getARGB();
        if (! is_string($argb) || in_array($argb, $this->cfg['legend']['ignore_argb'], true)) {
            return null;
        }

        return $argb;
    }

    /** The legend label for a data row, read from the first probe column that hits. */
    protected function rowStatusFlag(Worksheet $sheet, int $row, array $map, array $legend): ?string
    {
        if (! $legend) {
            return null;
        }

        foreach ($this->cfg['legend']['probe_columns'] as $field) {
            $col = $map[$field] ?? null;
            if (! is_int($col)) {
                continue;
            }
            $argb = $this->fillArgb($sheet, $row, $col);
            if ($argb !== null && isset($legend[$argb])) {
                return $legend[$argb];
            }
        }

        return null;
    }

    protected function canon(?string $sup, ?string $main, ?string $sub): ?string
    {
        $s = $this->norm($sup);
        $m = $this->norm($main);
        $b = $this->norm($sub);

        if (in_array($s, $this->cfg['ignore_super'], true)) {
            return null; // whole POSTING block
        }

        $h = $this->cfg['header'];

        if (array_key_exists($m, $h['by_main'])) {
            return $h['by_main'][$m];
        }
        if ($m === $h['dv_main']) {
            return 'dv';
        }
        // the only fixed field inside the deduction block
        if ($b === $h['status_sub_label'] && in_array($m, $h['status_allowed_main'], true)) {
            return 'status';
        }

        return null;
    }

    /* Human-readable column map, stored on the upload for audit. */
    protected function describeMap(array $map): array
    {
        $out = ['fields' => [], 'tax_details' => []];
        foreach ($map as $field => $col) {
            if ($field === '__tax__') {
                continue;
            }
            $out['fields'][$field] = Coordinate::stringFromColumnIndex($col);
        }
        foreach ($map['__tax__'] ?? [] as [$label, $col]) {
            $out['tax_details'][$label] = Coordinate::stringFromColumnIndex($col);
        }
        return $out;
    }

    // parsing

    protected function isNum(mixed $v): bool
    {
        return is_int($v) || is_float($v);
    }

    protected function isStr(mixed $v): bool
    {
        return is_string($v) && trim($v) !== '';
    }

    protected function get(array $row, string $field): mixed
    {
        return $row[$field] ?? null;
    }

    protected function parseRows(Worksheet $sheet, int $mainRow, array $map, int $year, array $legend = []): array
    {
        $records = [];
        $month   = null;
        $blanks  = 0;
        $last    = $sheet->getHighestDataRow();

        for ($r = $mainRow + 1; $r <= $last; $r++) {
            $row = [];
            foreach ($map as $field => $col) {
                if ($field === '__tax__') {
                    continue;
                }
                $row[$field] = $this->cellVal($sheet, $r, $col);
            }
            // the dynamic deduction block
            $tax = [];
            foreach ($map['__tax__'] ?? [] as [$label, $col]) {
                $v = $this->cellVal($sheet, $r, $col);
                if ($v !== null && $v !== '') {
                    $tax[$label] = $v;
                }
            }

            $type = $this->classify($row);

            if ($type === 'blank') {
                if (++$blanks > $this->cfg['blank_run_limit']) {
                    break;
                }
                continue;
            }
            $blanks = 0;

            if ($type === 'month_divider') {
                $month = $this->cfg['months'][$this->norm($this->get($row, 'payee'))] ?? $month;
            }

            $flag = $type === 'transaction'
                ? $this->rowStatusFlag($sheet, $r, $map, $legend)
                : null;

            $records[] = $this->extract($row, $tax, $r, $year, $month, $type, $flag);
        }

        return $records;
    }

    protected function classify(array $row): string
    {
        $payee    = $this->get($row, 'payee');
        $charging = $this->get($row, 'charging');

        if (isset($this->cfg['months'][$this->norm($payee)])) {
            return 'month_divider';
        }

        $keys = ['obr_prefix', 'obr_no', 'payee', 'charging', 'rc', 'particulars', 'gross', 'net', 'receipts'];
        $blank = true;
        foreach ($keys as $k) {
            $v = $this->get($row, $k);
            if ($v !== null && $v !== '' && $v !== ' ') {
                $blank = false;
                break;
            }
        }
        if ($blank) {
            return 'blank';
        }

        if ($this->isNum($this->get($row, 'receipts'))) {
            return 'allotment_header';
        }

        $mode     = $this->get($row, 'payment_mode');
        $hasMoney = $this->isNum($this->get($row, 'gross'))
            || $this->isNum($this->get($row, 'net'))
            || $this->isNum($this->get($row, 'charging_breakdown'));

        $blob = mb_strtoupper(implode(' ', array_map(
            fn ($k) => (string) $this->get($row, $k),
            ['payee', 'particulars', 'payment_mode']
        )));

        // an identity = payee, particulars, a charge code, or a payment mode.
        // column-total rows carry amounts but NO identity.
        $hasIdentity = $this->isStr($payee)
            || $this->isStr($this->get($row, 'particulars'))
            || $this->isStr($charging)
            || in_array($mode, ['A', 'C'], true);

        if ($this->matchesAny($blob, $this->cfg['markers']['subtotal'])
            || $this->isNum($charging)
            || ($hasMoney && ! $hasIdentity)) {
            return 'subtotal';
        }
        if ($this->matchesAny($blob, $this->cfg['markers']['section'])
            && ! ($this->isStr($charging) || $hasMoney)) {
            return 'section_header';
        }
        if ($hasIdentity && ($this->isStr($charging) || in_array($mode, ['A', 'C'], true) || $hasMoney)) {
            return 'transaction';
        }

        return 'unknown';
    }

    protected function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($haystack, $n)) {
                return true;
            }
        }
        return false;
    }

    protected function extract(array $row, array $tax, int $sourceRow, int $year, ?int $month, string $type, ?string $statusFlag = null): array
    {
        $rec = [
            'source_row'   => $sourceRow,
            'row_type'     => $type,
            'ledger_year'  => $year,
            'ledger_month' => $month,
            'status_flag'  => $statusFlag,
            'tax_details'  => [],
            'extras'       => [],
            'raw_row'      => [],
        ];

        foreach ($this->cfg['fields'] as $field) {
            $rec[$field] = $this->get($row, $field);
        }

        foreach ($this->cfg['date_fields'] as $df) {
            $rec[$df] = $this->toDate($rec[$df] ?? null);
        }

        // deduction block, keyed by whatever the sheet calls each column
        foreach ($tax as $label => $value) {
            $rec['tax_details'][$label] = $value;
        }

        // RC sometimes holds a numeric remittance amount instead of a code
        if ($this->isNum($rec['rc'] ?? null)) {
            $rec['extras']['rc_numeric'] = $rec['rc'];
            $rec['rc'] = null;
        }
        // payment_mode sometimes holds a subtotal label; keep only A/C
        if (! in_array($rec['payment_mode'] ?? null, ['A', 'C', null], true)) {
            $rec['extras']['payment_mode_raw'] = $rec['payment_mode'];
            $rec['payment_mode'] = null;
        }

        $rec['raw_row'] = ($this->cfg['store_raw_row'] ?? false)
            ? $row + ['__tax__' => $tax]
            : [];

        return $rec;
    }

    protected function toDate(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if ($this->isNum($v)) {
            return ExcelDate::excelToDateTimeObject($v)->format('Y-m-d');
        }
        return (string) $v;
    }

    /** Map a parsed record onto general_ledgers column names. */
    protected function toRow(array $rec, int $uploadId): array
    {
        $colMap = $this->cfg['column_map'];
        $out = [
            'upload_id'    => $uploadId,
            'source_row'   => $rec['source_row'],
            'row_type'     => $rec['row_type'],
            'ledger_year'  => $rec['ledger_year'],
            'ledger_month' => $rec['ledger_month'],
            'status_flag'  => $rec['status_flag'] ?? null,
            'tax_details'  => json_encode((object) $rec['tax_details']),
            'extras'       => json_encode((object) $rec['extras']),
            'raw_row'      => json_encode((object) $rec['raw_row']),
            'created_at'   => now(),
        ];

        foreach ($this->cfg['fields'] as $field) {
            $col = $colMap[$field] ?? $field;
            $out[$col] = $rec[$field] ?? null;
        }

        return $out;
    }
}