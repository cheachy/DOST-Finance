<?php

namespace Database\Seeders;

use App\Models\Upload;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Seeds account_references from the workbook's own `Ref` sheet, so the routing
 * table stays in sync with what the accountant actually maintains.
 *
 *   php artisan db:seed --class=AccountReferenceSeeder
 *
 * The Ref sheet has two columns: charging codes (A) and RC codes (C). It does
 * NOT carry sl_tab / allotment_class — those are derived here:
 *   - allotment_class: charging code -> PS | CO | MOOE  (else MOOE)
 *   - is_prior_year:   code names a prior-year set (NYDD / prior year payables)
 *   - sl_tab:          PHASE 1 routes only the three main tabs; everything else
 *                      is 'known but out of scope' (sl_tab = null, is_active = false)
 *                      so it does NOT masquerade as an unrouted error.
 */
class AccountReferenceSeeder extends Seeder
{
    /** Variants seen in the ledger that aren't in Ref -> canonical Ref code. */
    private const ALIASES = [
        'regular-onelab'                => 'Regular MOOE (Onelab)',
        'regular-emieerald'             => 'Regular MOOE (Emieerald)',
        'prior years payable'           => 'Prior Year Payables',
        'setup-non refund'              => 'SETUP Non Refund',
        'setup-non refund (s&t trngs)'  => 'SETUP Non Refund',
        'setup-pmb'                     => 'SETUP PMB',
        'saa-ifwd cntg'                 => 'SAA-SARAI cntg (MOOE)', // review with accountant
        'saa-ifwd'                      => 'IFWD',
        'saa-sarai cntg'                => 'SAA-SARAI cntg (MOOE)',
        'saa-sarai co cntg'             => 'SAA-SARAI cntg (CO)',
    ];

    /** Phase-1 routing: only these charging codes generate an SL tab. */
    private const SL_TABS = [
        'regular ps'   => 'PS',
        'regular mooe' => 'MOOE',
        'gia'          => 'GIA',
    ];

    public function run(): void
    {
        $codes = $this->readRefCharging();

        if (empty($codes)) {
            $this->command?->warn('Ref sheet unreadable or empty — seeding built-in fallbacks only.');
            $codes = array_values(self::SL_TABS ? ['Regular PS', 'Regular MOOE', 'GIA'] : []);
        }

        DB::transaction(function () use ($codes) {
            DB::table('account_references')->where('ref_type', 'charging')->delete();

            foreach ($codes as $code) {
                $norm = $this->norm($code);
                $tab  = self::SL_TABS[$norm] ?? null;

                DB::table('account_references')->updateOrInsert(
                    ['ref_type' => 'charging', 'code' => $code],
                    [
                        'allotment_class' => $this->classOf($code),
                        'is_prior_year'   => $this->isPriorYear($code),
                        'sl_tab'          => $tab,
                        'is_active'       => $tab !== null,   // in-scope only
                    ]
                );
            }

            // aliases point at the same routing as their canonical code
            foreach (self::ALIASES as $variant => $canonical) {
                $tab = self::SL_TABS[$this->norm($canonical)] ?? null;
                DB::table('account_references')->updateOrInsert(
                    ['ref_type' => 'charging', 'code' => $variant],
                    [
                        'label'           => $canonical,   // alias -> canonical
                        'allotment_class' => $this->classOf($canonical),
                        'is_prior_year'   => $this->isPriorYear($canonical),
                        'sl_tab'          => $tab,
                        'is_active'       => $tab !== null,
                    ]
                );
            }
        });

        $count = DB::table('account_references')->where('ref_type', 'charging')->count();
        $inScope = DB::table('account_references')->where('ref_type', 'charging')->whereNotNull('sl_tab')->count();
        $this->command?->info("Seeded {$count} charging references ({$inScope} in-scope for PS/MOOE/GIA).");
    }

    /** Read charging codes from the current snapshot's stored Ref sheet. */
    private function readRefCharging(): array
    {
        $upload = Upload::where('is_current', true)->where('status', 'done')->latest('id')->first();
        if (! $upload || ! $upload->stored_path || ! is_file($upload->stored_path)) {
            return [];
        }

        try {
            $sheet = IOFactory::load($upload->stored_path)->getSheetByName('Ref');
        } catch (\Throwable $e) {
            return [];
        }
        if (! $sheet) {
            return [];
        }

        $codes = [];
        foreach ($sheet->getRowIterator(2) as $row) {
            $v = $sheet->getCell('A' . $row->getRowIndex())->getValue();
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