<?php

namespace App\Http\Controllers;

use App\Models\AccountReference;
use App\Models\GeneralLedger;
use App\Models\Upload;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only subsidiary ledgers (PS / MOOE / GIA).
 *
 * Derived from the general ledger by charging code (via account_references) —
 * never stored, regenerated on every request from the live snapshot. Same
 * scoping and pagination shape as LedgerController; ?tab= picks the SL,
 * ?month= filters it, both live in the URL.
 */
class SlController extends Controller
{
    private const TABS = ['PS', 'MOOE', 'GIA'];

    public function index(Request $request): Response
    {
        $tab = strtoupper((string) $request->string('tab'));
        if (! in_array($tab, self::TABS, true)) {
            $tab = self::TABS[0];
        }

        $year = (int) date('Y');
        $upload = Upload::current($year);

        if (! $upload) {
            return Inertia::render('subsidiaryledgers/Index', [
                'hasLedger' => false,
                'tabs' => self::TABS,
                'tab' => $tab,
                'snapshot' => null,
                'months' => [],
                'month' => null,
                'rows' => null,
            ]);
        }

        $months = $upload->months();
        $month = $request->integer('month') ?: null;
        if ($month !== null && ! in_array($month, $months, true)) {
            $month = null;
        }

        $codes = AccountReference::query()->forSlTab($tab)->pluck('code');

        $rows = GeneralLedger::query()
            ->where('upload_id', $upload->id)
            ->whereIn('charging_code', $codes)
            ->when($month, fn ($q) => $q->where('ledger_month', $month))
            ->orderBy('source_row')
            ->paginate(100)
            ->withQueryString()
            ->through(fn (GeneralLedger $r) => [
                'source_row' => $r->source_row,
                'month' => $r->ledger_month,
                'obr' => $r->obrNumber(),
                'payee' => $r->payee,
                'charging' => $r->charging_code,
                'rc' => $r->rc_code,
                'particulars' => $r->particulars,
                'status' => $r->status,
                'status_flag' => $r->status_flag,
                'gross' => $r->gross_amount,
                'net' => $r->net_amount,
                'dv' => $r->dvNumber(),
                'payment_mode' => $r->payment_mode,
                'pay_date' => optional($r->payment_date)->format('Y-m-d'),
            ]);

        return Inertia::render('subsidiaryledgers/Index', [
            'hasLedger' => true,
            'tabs' => self::TABS,
            'tab' => $tab,
            'snapshot' => [
                'original_name' => $upload->original_name,
            ],
            'months' => $months,
            'month' => $month,
            'rows' => $rows,
        ]);
    }
}
