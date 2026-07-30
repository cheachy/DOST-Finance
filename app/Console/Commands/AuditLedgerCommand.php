<?php

namespace App\Console\Commands;

use App\Models\GeneralLedger;
use App\Models\Upload;
use Illuminate\Console\Command;

/**
 * php artisan ledger:audit {--year=2026}
 *
 * Traceability net. The messy source format means a few rows will never
 * classify cleanly - the goal is not perfect classification, it is that
 * NOTHING is ever silently lost and every row can be traced back to its
 * Excel source_row.
 *
 * This prints:
 *   1. a census of every row_type with counts (the row-accounting must balance)
 *   2. every 'unknown' row in full, so you can eyeball it against the workbook
 *   3. a contiguity check: which source_rows in the data range are ABSENT
 *      from the snapshot (i.e. were skipped as blank) - so a genuinely missing
 *      transaction can't hide behind "it was probably blank"
 */
class AuditLedgerCommand extends Command
{
    protected $signature = 'ledger:audit {--year=2026}';

    protected $description = 'Surface every non-transaction / unclassified row for review';

    public function handle(): int
    {
        $upload = Upload::current((int) $this->option('year'));
        if (! $upload) {
            $this->error('No current snapshot.');

            return self::FAILURE;
        }

        $base = GeneralLedger::where('upload_id', $upload->id);

        // 1. row-type census -------------------------------------------------
        $this->info("Snapshot #{$upload->id} — {$upload->original_name}");
        $this->newLine();
        $this->line('Row-type census:');

        $census = (clone $base)
            ->selectRaw('row_type, count(*) as n')
            ->groupBy('row_type')
            ->orderByDesc('n')
            ->pluck('n', 'row_type');

        $total = 0;
        foreach ($census as $type => $n) {
            $this->line(sprintf('   %-16s %6d', $type, $n));
            $total += $n;
        }
        $this->line(sprintf('   %-16s %6d', 'TOTAL stored', $total));
        $this->newLine();

        // 2. every unknown row, in full -------------------------------------
        $unknown = (clone $base)->where('row_type', 'unknown')->orderBy('source_row')->get();
        if ($unknown->isEmpty()) {
            $this->info('No unknown rows — every stored row classified cleanly.');
        } else {
            $this->warn("{$unknown->count()} unknown row(s) — trace each to the workbook:");
            foreach ($unknown as $r) {
                $bits = array_filter([
                    $r->payee ? "payee={$r->payee}" : null,
                    $r->particulars ? "part={$r->particulars}" : null,
                    $r->charging_code ? "chg={$r->charging_code}" : null,
                    $r->status ? "W={$r->status}" : null,
                    $r->gross_amount ? "gross={$r->gross_amount}" : null,
                    $r->net_amount ? "net={$r->net_amount}" : null,
                ]);
                $this->line(sprintf('   row %-5d %s', $r->source_row, $bits ? implode('  ', $bits) : '(all key fields empty)'));
            }
        }
        $this->newLine();

        // 3. contiguity: which source_rows are absent (skipped as blank) -----
        $rows = (clone $base)->orderBy('source_row')->pluck('source_row')->all();
        if (count($rows) >= 2) {
            $min = $rows[0];
            $max = end($rows);
            $present = array_flip($rows);
            $gaps = [];
            for ($i = $min; $i <= $max; $i++) {
                if (! isset($present[$i])) {
                    $gaps[] = $i;
                }
            }
            $this->line("Data range: source_row {$min}–{$max}, {$total} stored.");
            if ($gaps) {
                $this->line(count($gaps).' row(s) absent (skipped as blank): '
                    .$this->compactRanges($gaps));
                $this->line('If a transaction you expect is in that list, it was mis-skipped — investigate.');
            } else {
                $this->info('No gaps — every source_row in range is stored.');
            }
        }

        return self::SUCCESS;
    }

    /** Turn [12,13,14,20,21] into "12-14, 20-21" for readable output. */
    private function compactRanges(array $nums): string
    {
        $out = [];
        $start = $prev = $nums[0];
        foreach (array_slice($nums, 1) as $n) {
            if ($n === $prev + 1) {
                $prev = $n;

                continue;
            }
            $out[] = $start === $prev ? "$start" : "$start-$prev";
            $start = $prev = $n;
        }
        $out[] = $start === $prev ? "$start" : "$start-$prev";

        return implode(', ', $out);
    }
}
