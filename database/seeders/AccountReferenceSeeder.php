<?php

namespace Database\Seeders;

use App\Models\Upload;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Seeds account_references from the workbook's own `Ref` sheet.
 *
 *   php artisan db:seed --class=AccountReferenceSeeder
 *   (also runs automatically at the end of every import - see LedgerImportService)
 *
 * Safe to re-run, because it never invents or overwrites a SCOPE DECISION:
 *
 *   - CANONICAL codes (read fresh from Ref every run): allotment_class and
 *     is_prior_year are FACTS, always refreshed. sl_tab/is_active are set
 *     ONLY the first time a code is seen - after that, only
 *     `php artisan ledger:sl-tab` changes them.
 *
 *   - ALIAS codes (typo'd/inconsistent strings actually seen in the ledger,
 *     e.g. "Regular-Onelab") are NOT invented here at all. There is no
 *     hardcoded alias list in this file. An alias exists only because
 *     `php artisan ledger:alias add` created it. What THIS seeder does for
 *     an existing alias is keep its mirrored fields (allotment_class,
 *     sl_tab, is_active, is_prior_year) in sync with whatever its canonical
 *     row currently says - so if a canonical code's tab changes via
 *     ledger:sl-tab, every alias of it picks up the change automatically.
 */
class AccountReferenceSeeder extends Seeder
{
    /**
     * DEFAULT scope for a CANONICAL code the first time it is ever seen.
     * After that, this map is irrelevant - only ledger:sl-tab changes it.
     * This is what makes "enable a 4th tab" a data operation: seed once
     * with defaults, reassign via the command, and it sticks forever.
     */
    private const DEFAULT_SCOPE = [
        'regular ps' => 'PS',
        'regular mooe' => 'MOOE',
        'gia' => 'GIA',
    ];

    public function run(): void
    {
        $this->syncCanonical();
        $this->syncObserved();
        $this->mirrorAliases();

        $total = DB::table('account_references')->where('ref_type', 'charging')->count();
        $inScope = DB::table('account_references')->where('ref_type', 'charging')->where('is_active', true)->count();
        $aliases = DB::table('account_references')->where('ref_type', 'charging')->whereNotNull('canonical_code')->count();
        $this->command?->info("Seeded {$total} charging references ({$inScope} in-scope, {$aliases} aliases).");
    }

    /** Refresh every canonical code from the Ref sheet. */
    private function syncCanonical(): void
    {
        $codes = $this->readRefCharging();
        if (empty($codes)) {
            $this->command?->warn('Ref sheet unreadable or empty — seeding built-in fallbacks only.');
            $codes = ['Regular PS', 'Regular MOOE', 'GIA'];
        }

        $existing = DB::table('account_references')
            ->where('ref_type', 'charging')
            ->whereNull('canonical_code') // canonical rows only
            ->pluck('code')
            ->flip();

        foreach ($codes as $code) {
            $facts = [
                'allotment_class' => $this->classOf($code),
                'is_prior_year' => $this->isPriorYear($code),
                // Ref is authoritative. If this code was auto-discovered from
                // the ledger on an earlier run, appearing in Ref promotes it.
                'source' => 'ref',
            ];

            if (! $existing->has($code)) {
                $tab = self::DEFAULT_SCOPE[$this->norm($code)] ?? null;
                $facts['sl_tab'] = $tab;
                $facts['is_active'] = $tab !== null;
            }

            DB::table('account_references')->updateOrInsert(
                ['ref_type' => 'charging', 'code' => $code],
                $facts
            );
        }
    }

    /**
     * Register charging codes that the LEDGER uses but the Ref sheet does not.
     *
     * The Ref sheet is a curated list and the ledger is typed by hand, so the
     * two drift: "Green Wave" vs Ref's "SAA-Green Wave", "Regular-Onelab" vs
     * "Regular MOOE (Onelab)", "SAA-iFWD Cntg" vs "IFWD Cntg". Before this ran,
     * such a code matched no reference row, so it belonged to no allotment
     * class and contributed to NO ROD column - the only symptom was a total
     * that came out low. 14 codes on 61 transactions were being dropped that
     * way in CY2026.
     *
     * Nothing here is hardcoded and nothing is guessed by similarity. A code's
     * class comes from classOf() and its prior-year status from isPriorYear() -
     * the SAME rules already applied to every Ref code, read off the code
     * string itself. So next year's brand-new code classifies correctly on
     * import with no code change, no migration and no alias to remember.
     *
     * What is NOT derived is sl_tab: which subsidiary ledger a fund belongs to
     * is a scope decision, so these land unrouted and inactive, flagged
     * source='ledger' for the dashboard to put in front of her. Confirm one by
     * pointing it at its real code (`ledger:alias add`, which makes it an alias
     * and inherits the tab) or by giving it a tab (`ledger:sl-tab`).
     *
     * Only TRANSACTION rows are scanned. Subtotal rows carry a number in the
     * charging column (that is how the parser recognises them) and header rows
     * carry the literal "SL", none of which are funds.
     */
    private function syncObserved(): void
    {
        $upload = Upload::where('is_current', true)->where('status', 'done')->latest('id')->first();
        if (! $upload) {
            return;
        }

        $known = DB::table('account_references')
            ->where('ref_type', 'charging')
            ->pluck('code')
            ->flip();

        $codes = DB::table('general_ledgers')
            ->where('upload_id', $upload->id)
            ->where('row_type', 'transaction')
            ->whereNotNull('charging_code')
            ->where('charging_code', '<>', '')
            ->distinct()
            ->pluck('charging_code');

        $added = 0;
        foreach ($codes as $code) {
            if ($known->has($code) || is_numeric(trim($code))) {
                continue;
            }

            DB::table('account_references')->insert([
                'ref_type' => 'charging',
                'code' => $code,
                'label' => $code,
                'allotment_class' => $this->classOf($code),
                'is_prior_year' => $this->isPriorYear($code),
                'sl_tab' => null,
                'is_active' => false,
                'source' => 'ledger',
            ]);
            $added++;
        }

        if ($added) {
            $this->command?->warn("Discovered {$added} charging code(s) in the ledger that are not in the Ref sheet.");
            $this->command?->line('  Classified by rule and reported on the dashboard for review.');
        }
    }

    /**
     * Every alias row's scope is a mirror of its canonical row - refresh it
     * every run so a `ledger:sl-tab` change on the canonical propagates.
     */
    private function mirrorAliases(): void
    {
        $canonicalByCode = DB::table('account_references')
            ->where('ref_type', 'charging')
            ->whereNull('canonical_code')
            ->get(['code', 'allotment_class', 'sl_tab', 'is_active', 'is_prior_year'])
            ->keyBy('code');

        $aliases = DB::table('account_references')
            ->where('ref_type', 'charging')
            ->whereNotNull('canonical_code')
            ->get(['id', 'canonical_code']);

        foreach ($aliases as $alias) {
            $canonical = $canonicalByCode->get($alias->canonical_code);
            if (! $canonical) {
                // canonical was renamed/removed from Ref - leave the alias
                // alone rather than guessing; ledger:alias list will surface it.
                continue;
            }

            DB::table('account_references')->where('id', $alias->id)->update([
                'allotment_class' => $canonical->allotment_class,
                'is_prior_year' => $canonical->is_prior_year,
                'sl_tab' => $canonical->sl_tab,
                'is_active' => $canonical->is_active,
            ]);
        }
    }

    /** Read charging codes from the current snapshot's stored Ref sheet. */
    private function readRefCharging(): array
    {
        $upload = Upload::where('is_current', true)->where('status', 'done')->latest('id')->first();
        if (! $upload || ! $upload->stored_path || ! is_file($upload->stored_path)) {
            return [];
        }

        try {
            $reader = IOFactory::createReaderForFile($upload->stored_path);
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly(['Ref']);
            $sheet = $reader->load($upload->stored_path)->getSheetByName('Ref');
        } catch (\Throwable $e) {
            return [];
        }
        if (! $sheet) {
            return [];
        }

        $codes = [];
        foreach ($sheet->getRowIterator(2) as $row) {
            $v = $sheet->getCell('A'.$row->getRowIndex())->getValue();
            if (is_string($v) && trim($v) !== '') {
                $codes[] = trim($v);
            }
        }

        return array_values(array_unique($codes));
    }

    private function norm(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower($s)));
    }

    private function classOf(string $code): string
    {
        $u = mb_strtoupper($code);
        if ($u === 'REGULAR PS' || str_contains($u, 'PS DEFICIENCY') || str_contains($u, 'RLIP')) {
            return 'PS';
        }
        if ($u === 'CO' || str_contains($u, '(CO)') || str_contains($u, ' CO ')
            || str_ends_with($u, ' CO') || str_contains($u, 'MITHI CO')) {
            return 'CO';
        }

        return 'MOOE';
    }

    private function isPriorYear(string $code): bool
    {
        $u = mb_strtoupper($code);

        return str_contains($u, 'NYDD')
            || str_contains($u, 'PRIOR YEAR')
            || str_contains($u, 'PRIOR YEARS');
    }
}
