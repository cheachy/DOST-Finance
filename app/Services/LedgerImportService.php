<?php

namespace App\Services;

use App\Models\GeneralLedger;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Dynamic importer for the MDS 101 general ledger.
 *
 * Nothing about rows or columns is hardcoded:
 *   1. detectHeaderRow() finds the header band by its anchor labels.
 *   2. buildColumnMap() maps each column to a canonical field via config('ledger').
 *   3. parseRows() segments months by divider rows and classifies every row.
 *
 * Run this from a queued job - reading cached formula values across a large
 * workbook is CPU-bound.
 */
class LedgerImportService
{
    protected array $cfg;

    public function __construct()
    {
        $this->cfg = config('ledger');
    }

    public function import(string $absolutePath, int $year, string $originalName, ?int $uploadedBy = null): Upload
    {
        $upload = Upload::create([
            'original_name' => $originalName,
            'stored_path'   => $absolutePath,
            'sheet_name'    => $this->cfg['sheet'],
            'fiscal_year'   => $year,
            'status'        => 'parsing',
            'uploaded_by'   => $uploadedBy,
        ]);

        try {
            $sheet   = $this->loadSheet($absolutePath);
            $mainRow = $this->detectHeaderRow($sheet);
            $map     = $this->buildColumnMap($sheet, $mainRow);
            $records = $this->parseRows($sheet, $mainRow, $map, $year);

            DB::transaction(function () use ($records, $upload) {
                foreach (array_chunk($records, 500) as $chunk) {
                    GeneralLedger::insert(array_map(
                        fn ($rec) => $this->toRow($rec, $upload->id),
                        $chunk
                    ));
                }
            });

            $upload->update(['status' => 'done', 'row_count' => count($records)]);
        } catch (\Throwable $e) {
            $upload->update(['status' => 'failed']);
            throw $e;
        }

        return $upload;
    }

    // ---------------------------------------------------------------- loading

    protected function loadSheet(string $path): Worksheet
    {
        $reader = IOFactory::createReaderForFile($path);
        // keep formulas so we can read their cached results; skip styles for speed
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($path);

        return $spreadsheet->getSheetByName($this->cfg['sheet'])
            ?? $spreadsheet->getActiveSheet();
    }

    /** Read a cell's cached value (formula result if it is a formula). */
    protected function cellVal(Worksheet $sheet, int $row, int $col)
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

    // ------------------------------------------------------------ header band

    protected function norm($v): string
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
                if (! isset($labels[$a])) { $hit = false; break; }
            }
            if ($hit) {
                return $r;
            }
        }

        throw new \RuntimeException('Ledger header row not found');
    }

    /** coordinate -> top-left cached value, for merged header cells. */
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

    protected function bandValue(Worksheet $sheet, int $row, int $col, array $merges)
    {
        $direct = $this->cellVal($sheet, $row, $col);
        if ($direct !== null && $direct !== '') {
            return $direct;
        }
        $coord = Coordinate::stringFromColumnIndex($col) . $row;
        return $merges[$coord] ?? null;
    }

    /** canonical field -> 1-based column index, purely from header labels. */
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

        return $map;
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
        if (array_key_exists($b, $h['by_sub']) && in_array($m, $h['by_sub_allowed_main'], true)) {
            return $h['by_sub'][$b];
        }

        return null;
    }

    // --------------------------------------------------------------- parsing

    protected function isNum($v): bool
    {
        return is_int($v) || is_float($v);
    }

    protected function isStr($v): bool
    {
        return is_string($v) && trim($v) !== '';
    }

    protected function get(array $row, string $field)
    {
        return $row[$field] ?? null;
    }

    protected function parseRows(Worksheet $sheet, int $mainRow, array $map, int $year): array
    {
        $records = [];
        $month   = null;
        $blanks  = 0;
        $last    = $sheet->getHighestDataRow();

        for ($r = $mainRow + 1; $r <= $last; $r++) {
            $row = [];
            foreach ($map as $field => $col) {
                $row[$field] = $this->cellVal($sheet, $r, $col);
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

            $records[] = $this->extract($row, $r, $year, $month, $type);
        }

        return $records;
    }

    protected function classify(array $row): string
    {
        $payee    = $this->get($row, 'payee');
        $charging = $this->get($row, 'charging');
        $up       = $this->isStr($payee) ? mb_strtoupper(trim($payee)) : '';

        if (isset($this->cfg['months'][$this->norm($payee)])) {
            return 'month_divider';
        }

        $keys = ['obr_prefix', 'obr_no', 'payee', 'charging', 'rc', 'particulars', 'gross', 'net', 'receipts'];
        $blank = true;
        foreach ($keys as $k) {
            $v = $this->get($row, $k);
            if ($v !== null && $v !== '' && $v !== ' ') { $blank = false; break; }
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

        $hasIdentity = $this->isStr($payee)
            || $this->isStr($this->get($row, 'particulars'))
            || $this->isStr($charging)
            || in_array($mode, ['A', 'C'], true);

        // labelled total / pure-sum rows first
        if ($this->matchesAny($blob, $this->cfg['markers']['subtotal'])
            || $this->isNum($charging)
            || ($hasMoney && ! $hasIdentity)) {
            return 'subtotal';
        }
        // labelled section / annotation rows
        if ($this->matchesAny($blob, $this->cfg['markers']['section'])
            && ! ($this->isStr($charging) || $hasMoney)) {
            return 'section_header';
        }
        // a real transaction: identity + a charge code, a mode, or an amount
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

    protected function extract(array $row, int $sourceRow, int $year, ?int $month, string $type): array
    {
        $rec = [
            'source_row'   => $sourceRow,
            'row_type'     => $type,
            'ledger_year'  => $year,
            'ledger_month' => $month,
            'extras'       => [],
            'raw_row'      => [],
        ];

        foreach ($this->cfg['fields'] as $field) {
            $rec[$field] = $this->get($row, $field);
        }

        // convert Excel date serials / objects to Y-m-d
        foreach ($this->cfg['date_fields'] as $df) {
            $rec[$df] = $this->toDate($rec[$df] ?? null);
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

        $rec['raw_row'] = $row;

        return $rec;
    }

    protected function toDate($v): ?string
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
        return (string) $v; // already a string date
    }

    /** map a parsed record onto the general_ledgers column names. */
    protected function toRow(array $rec, int $uploadId): array
    {
        $colMap = $this->cfg['column_map'];
        $out = [
            'upload_id'    => $uploadId,
            'source_row'   => $rec['source_row'],
            'row_type'     => $rec['row_type'],
            'ledger_year'  => $rec['ledger_year'],
            'ledger_month' => $rec['ledger_month'],
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