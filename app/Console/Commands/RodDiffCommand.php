<?php

namespace App\Console\Commands;

use App\Models\GeneralLedger;
use App\Models\Upload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan ledger:rod-diff --month=3 --class=PS
 *
 * When ledger:rod-verify reports a mismatch, lists the actual rows behind
 * that month/class so the gap can be traced to a specific OBR - a split
 * remittance, a cancelled/reissued voucher, or something new - rather than
 * guessed at from a single total.
 */
class RodDiffCommand extends Command
{
    protected $signature = 'ledger:rod-diff {--year=2026} {--month=} {--class=}';

    protected $description = 'List the transaction rows behind a ROD month/class, to trace a mismatch';

    public function handle(): int
    {
        $year  = (int) $this->option('year');
        $month = $this->option('month') ? (int) $this->option('month') : null;
        $class = $this->option('class') ? strtoupper($this->option('class')) : null;

        if (! $month || ! $class) {
            $this->error('usage: ledger:rod-diff --month=3 --class=PS');
            return self::FAILURE;
        }

        $upload = Upload::current($year);
        if (! $upload) {
            $this->error("No current snapshot for FY{$year}.");
            return self::FAILURE;
        }

        $key = 'cur_' . strtolower($class); // matches rod_actual's key naming

        $codes = DB::table('account_references')
            ->where('ref_type', 'charging')
            ->where('allotment_class', $class)
            ->where('is_prior_year', false)
            ->pluck('code');

        $rows = GeneralLedger::where('upload_id', $upload->id)
            ->transactions()
            ->forMonth($month)
            ->disbursed()
            ->currentYearAllotment()
            ->whereIn('charging_code', $codes)
            ->orderBy('source_row')
            ->get(['source_row', 'obr_prefix', 'obr_no', 'payee', 'charging_code', 'net_amount', 'extras', 'rod_actual']);

        $this->info("Month {$month}, class {$class} — {$rows->count()} generated rows:");
        $this->line('  gen = net + numeric RC (the remittance half); see GeneralLedger::ROD_AMOUNT_SQL.');
        $genTotal = 0.0;
        $actTotal = 0.0;

        foreach ($rows as $r) {
            $obr = trim(($r->obr_prefix ?? '') . '-' . ($r->obr_no ?? ''), '-');
            $act = (float) ($r->rod_actual[$key] ?? 0);
            $gen = $r->rodAmount();
            $rc = (float) ($r->extras['rc_numeric'] ?? 0);
            $genTotal += $gen;
            $actTotal += $act;
            $flag = abs($act - $gen) > 0.01 ? '  <-- differs' : '';

            $this->line(sprintf(
                '  row %-5s OBR %-10s %-24s net=%12s rc=%12s gen=%12s actual_cell=%12s%s',
                $r->source_row, $obr, mb_substr($r->payee ?? '', 0, 22),
                number_format($r->net_amount, 2), number_format($rc, 2),
                number_format($gen, 2), number_format($act, 2), $flag
            ));
        }

        $this->newLine();
        $this->line('Generated total: ' . number_format($genTotal, 2));
        $this->line('Actual total:    ' . number_format($actTotal, 2));

        return self::SUCCESS;
    }
}