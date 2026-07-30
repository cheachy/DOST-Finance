<?php

namespace App\Services\Ledger;

use App\Services\Ledger\Concerns\ReadsWorksheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LedgerRowParser
{
    use ReadsWorksheet;

    protected array $cfg;

    public function __construct(
        protected LedgerLegendAnalyzer $legendAnalyzer
    ) {
        $this->cfg = config('ledger');
    }

    public function parseRows(Worksheet $sheet, int $mainRow, array $map, int $year, array $legend = []): array
    {
        $records = [];
        $month = null;
        $blanks = 0;
        $last = $sheet->getHighestDataRow();

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
                ? $this->legendAnalyzer->rowStatusFlag($sheet, $r, $map, $legend)
                : null;

            $records[] = $this->extract($row, $tax, $r, $year, $month, $type, $flag);
        }

        return $records;
    }

    public function toRow(array $rec, int $uploadId): array
    {
        $colMap = $this->cfg['column_map'];
        $out = [
            'upload_id' => $uploadId,
            'source_row' => $rec['source_row'],
            'row_type' => $rec['row_type'],
            'ledger_year' => $rec['ledger_year'],
            'ledger_month' => $rec['ledger_month'],
            'status_flag' => $rec['status_flag'] ?? null,
            'tax_details' => json_encode((object) $rec['tax_details']),
            'extras' => json_encode((object) $rec['extras']),
            'raw_row' => json_encode((object) $rec['raw_row']),
            'created_at' => now(),
        ];

        foreach ($this->cfg['fields'] as $field) {
            $col = $colMap[$field] ?? $field;
            $out[$col] = $rec[$field] ?? null;
        }

        return $out;
    }

    protected function classify(array $row): string
    {
        $payee = $this->get($row, 'payee');
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

        $mode = $this->get($row, 'payment_mode');
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

    protected function extract(array $row, array $tax, int $sourceRow, int $year, ?int $month, string $type, ?string $statusFlag = null): array
    {
        $rec = [
            'source_row' => $sourceRow,
            'row_type' => $type,
            'ledger_year' => $year,
            'ledger_month' => $month,
            'status_flag' => $statusFlag,
            'tax_details' => [],
            'extras' => [],
            'raw_row' => [],
        ];

        foreach ($this->cfg['fields'] as $field) {
            $rec[$field] = $this->get($row, $field);
        }

        foreach ($this->cfg['date_fields'] as $df) {
            $rec[$df] = $this->toDate($rec[$df] ?? null);
        }

        foreach ($tax as $label => $value) {
            $rec['tax_details'][$label] = $value;
        }

        if ($this->isNum($rec['rc'] ?? null)) {
            $rec['extras']['rc_numeric'] = $rec['rc'];
            $rec['rc'] = null;
        }
        if (! in_array($rec['payment_mode'] ?? null, ['A', 'C', null], true)) {
            $rec['extras']['payment_mode_raw'] = $rec['payment_mode'];
            $rec['payment_mode'] = null;
        }

        $rec['raw_row'] = ($this->cfg['store_raw_row'] ?? false)
            ? $row + ['__tax__' => $tax]
            : [];

        return $rec;
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

    protected function get(array $row, string $field): mixed
    {
        return $row[$field] ?? null;
    }
}
