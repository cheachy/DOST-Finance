<?php

namespace App\Http\Controllers;

use App\Models\GeneralLedger;
use App\Models\Upload;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Supplies the dashboard props.
 *
 * Every figure here is COMPUTED from the live snapshot - nothing about
 * allotment, disbursement, or utilization is stored.
 */
class DashboardController extends Controller
{
    public function index(): Response
    {
        $year   = (int) date('Y');
        $upload = Upload::current($year);

        return Inertia::render('auth/Dashboard', [
            'snapshot' => $upload ? [
                'id'                => $upload->id,
                'original_name'     => $upload->original_name,
                'fiscal_year'       => $upload->fiscal_year,
                'imported_at'       => $upload->created_at?->format('M j, Y g:i A'),
                'transaction_count' => $upload->transaction_count ?? 0,
                'months'            => $upload->months(),
            ] : null,

            'stats'    => $this->stats($upload),
            'alerts'   => $this->alerts($upload),
            'activity' => [],   // wired when activity_logs lands
        ]);
    }

    private function stats(?Upload $upload): array
    {
        $empty = ['allotted' => 0, 'disbursed' => 0, 'balance' => 0, 'utilization' => 0];

        if (! $upload || ! $upload->hasRows()) {
            return $empty;
        }

        $base = GeneralLedger::where('upload_id', $upload->id);

        // Allotment comes from the NCA / receipts rows, not from transactions.
        $allotted = (float) (clone $base)
            ->where('row_type', 'allotment_header')
            ->sum('receipts');

        // Disbursed = paid transactions only. A/C is the gate.
        $disbursed = (float) (clone $base)
            ->transactions()
            ->disbursed()
            ->sum('gross_amount');

        return [
            'allotted'    => $allotted,
            'disbursed'   => $disbursed,
            'balance'     => $allotted - $disbursed,
            'utilization' => $allotted > 0 ? round($disbursed / $allotted * 100, 2) : 0,
        ];
    }

    /**
     * Only surface what she can act on.
     *
     * Charging-less remittances and not-yet-due obligations are legitimate and
     * are NOT warnings - flagging them every import would train her to ignore
     * the panel.
     */
    private function alerts(?Upload $upload): array
    {
        if (! $upload || ! $upload->hasRows()) {
            return ['unrouted' => 0, 'stale' => false];
        }

        // Transactions whose charging code matches no known reference.
        // Codes that are known but out of Phase 1 scope are excluded here, so
        // they do not masquerade as errors.
        $unrouted = GeneralLedger::where('upload_id', $upload->id)
            ->transactions()
            ->whereNotNull('charging_code')
            ->whereNotExists(function ($q) {
                $q->select('id')
                  ->from('account_references')
                  ->whereColumn('account_references.code', 'general_ledgers.charging_code')
                  ->where('account_references.ref_type', 'charging');
            })
            ->count();

        return [
            'unrouted' => $unrouted,
            'stale'    => false,
        ];
    }
}