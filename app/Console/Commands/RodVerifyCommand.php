<?php

namespace App\Console\Commands;

use App\Models\GeneralLedger;
use App\Models\Upload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan ledger:rod-verify {--year=2026} {--month=} {--tolerance=0.01}
 *
 * Compares the GENERATED current-year ROD columns (charging_code decides
 * class, A/C gates inclusion, NET amount) against what she actually typed
 * into AR/AS/AT of MDS 101, captured at import time into rod_actual.
 *
 * CURRENT YEAR ONLY. Prior-year is never generated - she has confirmed there
 * is no derivation rule for prior-year PS/MOOE/CO, so a generated number
 * there would just coincidentally match her manual split, not validate it.
 *
 * The ROD is a MONTHLY report, unlike the dashboard - do NOT make this
 * cumulative. A mismatch is not automatically a bug: known causes include a
 * remittance split across multiple ROD columns by hand (round-2 A4) and a
 * cancelled/reissued OBR she may have manually netted (round-2 B7). Use
 * ledger:rod-diff to see the actual rows behind any failure.
 */
class RodVerifyCommand extends Command
{
    protected $signature = 'ledger:rod-verify {--year=2026} {--month=} {--tolerance=0.01}';

    protected $description = 'Compare generated current-year ROD columns against her actual MDS 101 values';

    private const CLASSES = ['ps' => 'PS', 'mooe' => 'MOOE', 'co' => 'CO'];

    public function handle(): int
    {
        $year = (int) $this->option('year');
        $tolerance = (float) $this->option('tolerance');

        $upload = Upload::current($year);
        if (! $upload) {
            $this->error("No current snapshot for FY{$year}. Import a workbook first.");
            return self::FAILURE;
        }

        $months = $this->option('month')
            ? [(int) $this->option('month')]
            : $upload->months();

        $this->info("Snapshot #{$upload->id} — {$upload->original_name}");
        $this->newLine();

        $failures = [];

        foreach ($months as $month) {
            $this->line("<fg=cyan>Month {$month}</>");

            foreach (self::CLASSES as $key => $label) {
                $generated = $this->generated($upload->id, $month, $label);
                $actual = $this->actual($upload->id, $month, 'cur_' . $key);
                $diff = round($generated - $actual, 2);
                $ok = abs($diff) < $tolerance;

                $this->line(sprintf(
                    '   %s %-6s generated %12s   actual %12s   diff %10s',
                    $ok ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
                    $label,
                    number_format($generated, 2),
                    number_format($actual, 2),
                    number_format($diff, 2)
                ));

                if (! $ok) {
                    $failures[] = compact('month', 'label', 'generated', 'actual', 'diff');
                }
            }
            $this->newLine();
        }

        $unclassified = $this->unclassified($upload->id);

        if ($unclassified->isNotEmpty()) {
            $this->warn($unclassified->count() . ' charging code(s) match no account_references row:');
            foreach ($unclassified as $code => $rows) {
                $total = $rows->sum(fn ($r) => $r->rodAmount());
                $this->line(sprintf(
                    '   %-32s %2d row(s)  %14s   months %s',
                    $code,
                    $rows->count(),
                    number_format($total, 2),
                    $rows->pluck('ledger_month')->unique()->sort()->implode(',')
                ));
            }
            $this->line('These contribute to NO class. Register each as data:');
            $this->line('   php artisan ledger:alias add "<as typed>" "<canonical code>"');
            $this->newLine();
        }

        if ($failures) {
            $this->reportAnomalies($upload->id);

            $this->warn(count($failures) . ' month/class combination(s) mismatched.');
            $this->line('Run: php artisan ledger:rod-diff --month=N --class=PS   to see the actual rows.');
            return self::FAILURE;
        }

        $this->info('All current-year ROD figures match her actual MDS 101 values.');
        return self::SUCCESS;
    }

    /** Independently computed: charging code decides class, A/C gates inclusion. */
    private function generated(int $uploadId, int $month, string $class): float
    {
        return (float) GeneralLedger::where('upload_id', $uploadId)
            ->transactions()
            ->forMonth($month)
            ->disbursed()
            ->currentYearAllotment()
            ->whereIn('charging_code', function ($q) use ($class) {
                $q->select('code')->from('account_references')
                  ->where('ref_type', 'charging')
                  ->where('allotment_class', $class)
                  ->where('is_prior_year', false);
            })
            ->sum(DB::raw(GeneralLedger::ROD_AMOUNT_SQL));
    }

    /** Print the anomaly buckets, so a FAIL always says which rows caused it. */
    private function reportAnomalies(int $uploadId): void
    {
        [
            'unbacked' => $unbacked,
            'unrouted' => $unrouted,
            'reclassed' => $reclassed,
        ] = $this->anomalies($uploadId);

        $fmt = fn (array $typed) => implode(' + ', array_map(
            fn ($v, $k) => $k . ' ' . number_format($v, 2),
            $typed,
            array_keys($typed)
        ));

        if ($unbacked) {
            $this->warn(count($unbacked) . ' row(s) with a ROD figure but NO disbursement behind it (open question B7):');
            foreach ($unbacked as [$r, $typed]) {
                $this->line(sprintf(
                    '   row %-5s M%-2d she typed %-22s net+rc 0.00   returned %13s   %s',
                    $r->source_row, $r->ledger_month, $fmt($typed),
                    number_format((float) $r->receipts, 2),
                    $r->status_flag ? '['.$r->status_flag.']' : ''
                ));
            }
            $this->line('   A cancelled payment whose money came back, then was reissued. Her ROD');
            $this->line('   still counts it, and it is also counted in the month it was first paid.');
            $this->line('   Whether the later month nets it off, or the original month reverses, is');
            $this->line('   hers to decide - do NOT net these off by guessing.');
            $this->newLine();
        }

        if ($unrouted) {
            $this->warn(count($unrouted) . ' row(s) with NO charging code, split by hand (open question A4):');
            foreach ($unrouted as [$r, $typed]) {
                $this->line(sprintf(
                    '   row %-5s M%-2d gen %13s   she typed %-40s  %s',
                    $r->source_row, $r->ledger_month, number_format($r->rodAmount(), 2),
                    $fmt($typed), mb_substr((string) $r->particulars, 0, 44)
                ));
            }
            $this->line('   No rule can derive these: the split follows which supplier the money');
            $this->line('   was withheld from, which the row does not record. Ask her for the rule.');
            $this->newLine();
        }

        if ($reclassed) {
            $this->warn(count($reclassed) . ' row(s) whose charging code disagrees with the column she typed:');
            foreach ($reclassed as [$r, $mine, $typed]) {
                $this->line(sprintf(
                    '   row %-5s M%-2d %-24s Ref sheet says %-5s   she typed %s',
                    $r->source_row, $r->ledger_month,
                    mb_substr((string) $r->charging_code, 0, 22), $mine, $fmt($typed)
                ));
            }
            $this->line('   Her Ref sheet and her ROD disagree. If the ROD is right, correct the');
            $this->line('   Ref sheet class; if the Ref sheet is right, the ROD cell is a typo.');
            $this->newLine();
        }
    }

    /**
     * Rows the generator provably cannot reproduce, and why.
     *
     * Every remaining peso of a FAIL should be listed here. A mismatch with
     * nothing reported is a real bug; a mismatch fully accounted for below is
     * a question for the accountant, not a defect.
     *
     * Two buckets, both confirmed against MDS 101 on 2026-08-13:
     *
     *   unrouted  - no charging code at all. These are the withheld-money
     *               remittances ("retention fee / liquidated damages / local
     *               tax withheld from suppliers"), which she splits BY HAND
     *               across current MOOE, current CO and prior year according
     *               to which supplier invoices the money was originally held
     *               back from. That provenance is not on the row, so there is
     *               nothing to derive it from - this is open question A4.
     *
     *   reclassed - the charging code's class (per the Ref sheet) is not the
     *               column she actually typed into. Only one row does this in
     *               CY2026, and it is a real disagreement between two of her
     *               own documents, so it is surfaced rather than papered over.
     */
    private function anomalies(int $uploadId): array
    {
        $classOf = DB::table('account_references')
            ->where('ref_type', 'charging')
            ->where('is_prior_year', false)
            ->pluck('allotment_class', 'code');

        $unbacked = [];
        $unrouted = [];
        $reclassed = [];

        $rows = GeneralLedger::where('upload_id', $uploadId)
            ->transactions()
            ->disbursed()
            ->currentYearAllotment()
            ->orderBy('source_row')
            ->get([
                'source_row', 'ledger_month', 'charging_code', 'particulars',
                'net_amount', 'receipts', 'status_flag', 'extras', 'rod_actual',
            ]);

        foreach ($rows as $r) {
            $typed = [];
            foreach (self::CLASSES as $key => $label) {
                $v = (float) ($r->rod_actual['cur_' . $key] ?? 0);
                if (abs($v) > 0.01) {
                    $typed[$label] = $v;
                }
            }

            // A ROD figure on a row that disbursed nothing (net + numeric RC
            // both zero). The money came back: a cancelled cheque refunded in
            // a later month, its return sitting in RECEIPTS. Her ROD still
            // carries the amount, so generated and actual cannot agree until
            // B7 says which side should move.
            if ($typed && abs($r->rodAmount()) < 0.01) {
                $unbacked[] = [$r, $typed];

                continue;
            }

            if (trim((string) $r->charging_code) === '') {
                if ($typed) {
                    $unrouted[] = [$r, $typed];
                }

                continue;
            }

            $mine = $classOf[$r->charging_code] ?? null;
            if ($mine && $typed && ! array_key_exists($mine, $typed)) {
                $reclassed[] = [$r, $mine, $typed];
            }
        }

        return compact('unbacked', 'unrouted', 'reclassed');
    }

    /**
     * Disbursed rows whose charging code is in no account_references row.
     *
     * These used to vanish: generated() matches on a whitelist, so a code the
     * Ref sheet spells differently from the ledger ("Green Wave" vs
     * "SAA-Green Wave") contributed nothing to any class and the only symptom
     * was a total that came out low. Report them instead - the fix is data,
     * not code: php artisan ledger:alias add "<as typed>" "<canonical>".
     */
    private function unclassified(int $uploadId): \Illuminate\Support\Collection
    {
        return GeneralLedger::where('upload_id', $uploadId)
            ->transactions()
            ->disbursed()
            ->currentYearAllotment()
            ->whereNotNull('charging_code')
            ->where('charging_code', '<>', '')
            ->whereNotIn('charging_code', function ($q) {
                $q->select('code')->from('account_references')->where('ref_type', 'charging');
            })
            ->get(['source_row', 'ledger_month', 'charging_code', 'net_amount', 'extras'])
            ->groupBy('charging_code');
    }

    /** What she actually typed into the ROD block, captured at import time. */
    private function actual(int $uploadId, int $month, string $key): float
    {
        return (float) GeneralLedger::where('upload_id', $uploadId)
            ->transactions()
            ->forMonth($month)
            ->get(['rod_actual'])
            ->sum(fn ($r) => (float) ($r->rod_actual[$key] ?? 0));
    }
}