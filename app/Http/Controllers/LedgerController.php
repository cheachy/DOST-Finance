<?php

namespace App\Http\Controllers;

use App\Models\GeneralLedger;
use App\Models\Upload;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only view of the live general-ledger snapshot.
 *
 * Always scoped to the current snapshot and returned in SHEET ORDER
 * (source_row) — a ledger without an explicit order is not a ledger.
 * An optional ?month= filters to a single month.
 */
class LedgerController extends Controller
{
    public function index(Request $request): Response
    {
        $year = (int) date('Y');
        $upload = Upload::current($year);

        if (! $upload) {
            return Inertia::render('ledger/Index', [
                'hasLedger' => false,
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

        $rows = GeneralLedger::query()
            ->where('upload_id', $upload->id)
            ->when($month, fn ($q) => $q->where('ledger_month', $month))
            ->orderBy('source_row')
            ->paginate(100)
            ->withQueryString()
            ->through(fn (GeneralLedger $r) => [
                'source_row' => $r->source_row,
                'row_type' => $r->row_type,
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

        return Inertia::render('ledger/Index', [
            'hasLedger' => true,
            'snapshot' => [
                'original_name' => $upload->original_name,
                'transaction_count' => $upload->transaction_count,
            ],
            'months' => $months,
            'month' => $month,
            'rows' => $rows,
        ]);
    }
}
