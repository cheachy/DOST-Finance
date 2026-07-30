<?php

namespace App\Http\Controllers;

use App\Models\AccountReference;
use App\Models\GeneralLedger;
use App\Models\SlTab;
use App\Models\Upload;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only subsidiary ledgers.
 *
 * Which tabs exist comes from `sl_tabs` (see SlTab model / `ledger:sl-tab`
 * command), NOT a hardcoded list here. Adding a 4th tab is a data operation;
 * this controller does not need to change.
 *
 * Derived from the general ledger by charging code (via account_references)
 * — never stored, regenerated on every request from the live snapshot. Same
 * scoping/pagination shape as LedgerController; ?tab= picks the SL, ?month=
 * filters it, both live in the URL.
 */
class SlController extends Controller
{
    public function index(Request $request): Response
    {
        $tabs = SlTab::active(); // ordered by display_order, whatever tabs currently exist

        if ($tabs->isEmpty()) {
            return Inertia::render('subsidiaryledgers/Index', [
                'hasLedger' => false,
                'tabs' => [],
                'tab' => null,
                'snapshot' => null,
                'months' => [],
                'month' => null,
                'rows' => null,
            ]);
        }

        $requested = strtoupper((string) $request->string('tab'));
        $tab = $tabs->firstWhere('code', $requested)?->code ?? $tabs->first()->code;

        $year = (int) date('Y');
        $upload = Upload::current($year);

        if (! $upload) {
            return Inertia::render('subsidiaryledgers/Index', [
                'hasLedger' => false,
                'tabs' => $tabs->map(fn ($t) => ['code' => $t->code, 'label' => $t->label])->values(),
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

        // Every charging code currently in account_references carries
        // charging_code only on real transaction rows in this workbook
        // (confirmed 2026-07-29), so this whereIn is already transaction-only
        // by construction. transactions() is added anyway as a defensive
        // guarantee against a FUTURE classify() regression re-populating
        // charging_code on a non-transaction row - it should never change
        // today's result, only prevent a future silent leak.
        $codes = AccountReference::query()->forSlTab($tab)->pluck('code');

        $rows = GeneralLedger::query()
            ->where('upload_id', $upload->id)
            ->transactions()
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
            'tabs' => $tabs->map(fn ($t) => ['code' => $t->code, 'label' => $t->label])->values(),
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
