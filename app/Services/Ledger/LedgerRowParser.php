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
            'rod_actual' => json_encode((object) ($rec['rod_actual'] ?? [])),
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

        // A number in RECEIPTS normally means an NCA/NTA release - money coming
        // IN, which is what an allotment header records.
        //
        // Unless the row is also a disbursement. 2026-08-13: source_row 1777
        // carries OBR 04-0762, DV 04-677, payment mode C, a payee, a charging
        // code and her own ROD entry - it is a payment whose check was
        // cancelled, the money returned in May (hence the RECEIPTS value) and
        // then reissued on row 1778. Classifying it as an allotment header put
        // that 26,900.00 refund into TOTAL FUNDS ALLOTTED, as though the
        // department had been granted more budget.
        //
        // A real allotment header has NO obligation behind it: all 20 genuine
        // ones in CY2026 are payee "NCA-..."/"NTA ..." with no OBR, no DV and
        // no A/C. Verified by scanning all 21 - this predicate moves exactly
        // one row, so it cannot silently reshuffle the census.
        //
        // NOTE this makes the row a transaction; it does NOT decide how the
        // refund should hit the ROD. That is open question B7 (cancelled /
        // reissued pairs) and is still hers to answer.
        if ($this->isNum($this->get($row, 'receipts')) && ! $this->hasDisbursementIdentity($row)) {
            return 'allotment_header';
        }

        $mode = $this->get($row, 'payment_mode');
        $hasMoney = $this->isNum($this->get($row, 'gross'))
            || $this->isNum($this->get($row, 'net'))
            || $this->isNum($this->get($row, 'charging_breakdown'));

        // Marker matching prefers PAYEE, falling back to PARTICULARS only when
        // payee is blank.
        //
        // Every confirmed subtotal/header row either carries its label in
        // payee with particulars blank (e.g. payee="ACCOUNTING"), OR has no
        // payee at all and the label sits in particulars instead (e.g.
        // payee=null, particulars="TOTAL TRA..."). Genuine transactions
        // always have a real payee - so scanning particulars ONLY when payee
        // is empty catches real header/subtotal rows without reopening the
        // false-positive problem: every false positive found so far (source_
        // row 343, 954, 1601, 1960) had a real, non-blank payee, so the
        // fallback never engages for them.
        //
        // Do not widen this to "payee + particulars, always" - that is what
        // caused the original bug (ordinary transaction narrative containing
        // TOTAL/BUDGET/etc got scanned and false-matched).
        $particulars = $this->get($row, 'particulars');
        $blob = mb_strtoupper((string) ($this->isStr($payee) ? $payee : $particulars));

        // an identity = payee, particulars, a charge code, or a payment mode.
        // column-total rows carry amounts but NO identity.
        $hasIdentity = $this->isStr($payee)
            || $this->isStr($particulars)
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

        // Her actual ROD figures, captured as typed - never generated. Current-year
        // only matters for comparison (ledger:rod-verify); prior-year is captured
        // too, purely for reference, since there is no rule to generate it against.
        //
        // A/C-GATED, same as generation. The summary block under each month (A/C
        // breakdown, PS TAX/ACCTG, monthly totals) physically shares rows with the
        // NYDD vendor listing - those rows have real payees and classify as
        // transactions, but their AR-AW values are summary figures, not per-row
        // ROD entries. Verified 2026-08-13: every genuine per-row ROD value sits
        // on a row whose A/C column is 'A' or 'C'; every contaminated row does not.
        // Gate on the RAW payment mode - the extras-normalization below nulls
        // non-A/C modes, so this must run first.
        $rawMode = $this->get($row, 'payment_mode');
        $rawMode = is_string($rawMode) ? trim($rawMode) : $rawMode;

        $rec['rod_actual'] = in_array($rawMode, ['A', 'C'], true)
            ? [
                'cur_ps'     => $this->get($row, 'cur_ps'),
                'cur_mooe'   => $this->get($row, 'cur_mooe'),
                'cur_co'     => $this->get($row, 'cur_co'),
                'prior_ps'   => $this->get($row, 'prior_ps'),
                'prior_mooe' => $this->get($row, 'prior_mooe'),
                'prior_co'   => $this->get($row, 'prior_co'),
            ]
            : [];

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

    /**
     * An OBR number AND a DV number AND an A/C payment mode, together.
     *
     * All three, because any one alone is too weak: plenty of legitimate
     * non-transaction rows carry one. Together they describe a specific
     * obligation paid by a specific voucher through a specific instrument,
     * which no NCA/NTA release ever has.
     *
     * Mirrors GeneralLedger::obrNumber()/dvNumber(), which treat the prefix
     * and the number as two halves of one identifier - either half present is
     * enough for that half to count.
     */
    protected function hasDisbursementIdentity(array $row): bool
    {
        $mode = $this->get($row, 'payment_mode');
        $mode = is_string($mode) ? trim($mode) : $mode;

        if (! in_array($mode, ['A', 'C'], true)) {
            return false;
        }

        $hasObr = $this->present($row, 'obr_prefix') || $this->present($row, 'obr_no');
        $hasDv = $this->present($row, 'dv_prefix') || $this->present($row, 'dv_no');

        return $hasObr && $hasDv;
    }

    /** A field that is neither null nor whitespace, whatever its type. */
    protected function present(array $row, string $field): bool
    {
        $v = $this->get($row, $field);

        return $v !== null && trim((string) $v) !== '';
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
