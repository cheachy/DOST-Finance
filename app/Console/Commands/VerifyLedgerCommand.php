<?php

namespace App\Console\Commands;

use App\Models\GeneralLedger;
use App\Models\Upload;
use Illuminate\Console\Command;

/**
 * php artisan ledger:verify {--year=2026}
 *
 * Proves the PHP importer agrees with the Python reference parser, which was
 * validated against the CY2026 workbook and the sheet's own subtotal rows.
 *
 * The January figures below are not invented: they are the MDS 101 subtotal at
 * row 122, matched to the centavo by the reference parser.
 */
class VerifyLedgerCommand extends Command
{
    protected $signature = 'ledger:verify {--year=2026} {--strict}';

    protected $description = 'Verify the imported general ledger against known-good reference figures';

    /** Reference figures from the validated CY2026 parse (7 months, Jan-Jul). */
    private const REF = [
        'rows' => 2376,
        // 2026-07-29 FINAL: was 2130 -> 2136 -> 2141. Two real bugs in
        // classify(), both found via unexpected undercounts and fixed with
        // evidence, not guesses:
        //   1. 'ACCOUNTING' marker matched real transactions whose
        //      particulars merely mentioned the accounting office (+6 rows).
        //   2. Marker matching scanned particulars at all - ordinary English
        //      words that are also markers (TOTAL, BUDGET) false-matched real
        //      transaction narrative ("...total organic carbon testing...",
        //      "...budget proposal review..."). Fixed by matching PAYEE first,
        //      falling back to particulars only when payee is blank (+4 rows,
        //      -1 net vs a naive count, since one recovered row landed as a
        //      genuine subtotal rather than a transaction).
        // 2026-08-13: 2141 -> 2142. A third classify() bug, same shape as the
        // two above (a rule that was too eager, found via a number that came
        // out wrong rather than by inspection). A numeric RECEIPTS value was
        // enough on its own to call a row an allotment header, so source_row
        // 1777 - a cancelled-and-refunded payment carrying OBR 04-0762, DV
        // 04-677 and mode C - was booked as an NCA release, putting its
        // 26,900.00 refund into TOTAL FUNDS ALLOTTED. Now a numeric RECEIPTS
        // value only means "allotment header" on a row with no obligation
        // behind it. Verified against all 21 headers before the change: the
        // predicate moves exactly one row (the other 20 are NCA/NTA releases
        // with no OBR, no DV, no A/C).
        // Confirmed via ledger:audit: row-type census still sums to exactly
        // 2376 (2142+175+23+20+9+7), so every physical row is accounted for.
        'transactions' => 2142,
        'months' => 7,
        'jan_block1_gross' => 3453752.81,
        'jan_block1_net' => 3708031.72,
        'jan_block1_rows' => 104,   // January rows before the r122 subtotal
        // 2026-07-29: 628 -> 632 -> 634, tracking the transaction-count fixes
        // above (status_flag is only computed for row_type=transaction).
        // 2026-08-13: 634 -> 635. Row 1777 became a transaction, so its legend
        // fill is now resolved - and it reads CANCELLED, which is exactly what
        // a cancelled-then-reissued payment should carry. That the flag agrees
        // with the reclassification is corroboration, not a coincidence.
        'status_flag_rows' => 635,
        'tax_keys' => 8,
    ];

    public function handle(): int
    {
        $year = (int) $this->option('year');

        $upload = Upload::current($year);
        if (! $upload) {
            $this->error("No current snapshot for FY{$year}. Import a workbook first.");

            return self::FAILURE;
        }

        $this->info("Snapshot #{$upload->id} — {$upload->original_name}");
        $this->line('  imported: '.$upload->created_at?->toDateTimeString());
        $this->newLine();

        $rows = GeneralLedger::where('upload_id', $upload->id);
        $tx = (clone $rows)->transactions();

        $checks = [];

        $checks[] = $this->check('total rows', (clone $rows)->count(), self::REF['rows']);
        $checks[] = $this->check('transactions', (clone $tx)->count(), self::REF['transactions']);
        $checks[] = $this->check('months', count($upload->months()), self::REF['months']);

        // January block 1: rows before the sheet's own subtotal at row 122.
        $jan = (clone $tx)->forMonth(1)->where('source_row', '<', 122);
        $checks[] = $this->check('jan block-1 rows', (clone $jan)->count(), self::REF['jan_block1_rows']);
        $checks[] = $this->checkMoney('jan block-1 gross', (float) (clone $jan)->sum('gross_amount'), self::REF['jan_block1_gross']);
        $checks[] = $this->checkMoney('jan block-1 net', (float) (clone $jan)->sum('net_amount'), self::REF['jan_block1_net']);

        $checks[] = $this->check(
            'rows with status_flag',
            (clone $tx)->whereNotNull('status_flag')->count(),
            self::REF['status_flag_rows']
        );

        // distinct tax_details keys discovered by the dynamic sweep
        $keys = [];
        foreach ((clone $tx)->whereRaw("tax_details <> '{}'::jsonb")->pluck('tax_details') as $d) {
            foreach ((array) $d as $k => $_) {
                $keys[$k] = true;
            }
        }
        $checks[] = $this->check('tax_details keys', count($keys), self::REF['tax_keys']);

        $this->newLine();
        $this->line('discovered tax columns: '.implode(', ', array_keys($keys)));

        // things that should be zero
        $unknown = (clone $rows)->where('row_type', 'unknown')->count();
        // 2026-07-29: audited via ledger:audit and traced individually - all 9
        // are benign (7 blank spacer rows, 1 unworded section header, 1
        // reversion note), none are transactions. 9 is the confirmed baseline
        // for THIS workbook; a different count on a future import is worth a
        // fresh look via ledger:audit, but isn't a failure on its own.
        $this->line("unknown rows: {$unknown}".($unknown > 9 ? '  <- more than the audited baseline, check ledger:audit' : ''));

        $this->newLine();
        $failed = count(array_filter($checks, fn ($ok) => ! $ok));

        if ($failed === 0) {
            $this->info('All checks passed — the PHP importer matches the reference parse.');

            return self::SUCCESS;
        }

        $this->error("{$failed} check(s) failed.");
        $this->line('A mismatch means the PHP port diverged from the validated logic.');

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }

    private function check(string $label, int $actual, int $expected): bool
    {
        $ok = $actual === $expected;
        $this->line(sprintf(
            '  %s %-24s %10s   expected %s',
            $ok ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
            $label,
            number_format($actual),
            number_format($expected)
        ));

        return $ok;
    }

    private function checkMoney(string $label, float $actual, float $expected): bool
    {
        $ok = abs($actual - $expected) < 0.01;
        $this->line(sprintf(
            '  %s %-24s %10s   expected %s',
            $ok ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
            $label,
            number_format($actual, 2),
            number_format($expected, 2)
        ));

        return $ok;
    }
}
