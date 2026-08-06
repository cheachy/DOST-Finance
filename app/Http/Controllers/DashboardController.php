<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\GeneralLedger;
use App\Models\Upload;
use Illuminate\Http\Request;
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
    public function index(Request $request): Response
    {
        $year   = (int) date('Y');
        $upload = Upload::current($year);
        $months = $upload?->months() ?? [];

        // Selecting a month scopes the headline figures to AS OF THAT MONTH
        // (cumulative Jan-through-month), matching the AS-OF-{MONTH} block
        // in the workbook's own SL summaries. Confirmed 2026-07-30: this
        // genuinely changes the numbers, it isn't cosmetic - the month
        // chips must actually reload the page. See stats() for why both
        // sides of the calculation must be cumulative together.
        $month = $request->integer('month') ?: null;
        if ($month !== null && ! in_array($month, $months, true)) {
            $month = null;
        }

        return Inertia::render('auth/Dashboard', [
            'snapshot' => $upload ? [
                'id'                => $upload->id,
                'original_name'     => $upload->original_name,
                'fiscal_year'       => $upload->fiscal_year,
                'imported_at'       => $upload->created_at?->format('M j, Y g:i A'),
                'transaction_count' => $upload->transaction_count ?? 0,
                'months'            => $months,
            ] : null,

            'month'        => $month,
            'stats'        => $this->stats($upload, $month),
            'monthlyTrend' => $this->monthlyTrend($upload),
            'alerts'       => $this->alerts($upload),
            'activity'     => $this->activity(),
        ]);
    }

    /**
     * Newest five events off the same append-only trail as the full
     * /logs page - imports, sign-ins, exports.
     */
    private function activity(): array
    {
        return ActivityLog::orderByDesc('created_at')
            ->limit(12)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id'     => $log->id,
                'level'  => $log->level,
                'title'  => $log->title,
                'detail' => $log->description ?? '',
                'at'     => $log->created_at?->diffForHumans(),
            ])
            ->all();
    }

    private function stats(?Upload $upload, ?int $month): array
    {
        $empty = ['allotted' => 0, 'disbursed' => 0, 'balance' => 0, 'utilization' => 0];

        if (! $upload || ! $upload->hasRows()) {
            return $empty;
        }

        // Balance and utilization are "AS OF {MONTH}" figures, same as her
        // own SL summaries - cumulative allotted and cumulative disbursed
        // through the same month, never a mix of one cumulative and one
        // monthly side. "All" is just AS OF December, not a separate case.
        // Confirmed 2026-07-30: mixing cumulative allotted with month-only
        // disbursed produced nonsense in both directions - March showed
        // 164% utilization (a whole month's spend charged against only
        // that month's receipts), July showed a huge phantom balance
        // (cumulative receipts minus only July's spend, ignoring six
        // months of real spending in between).
        $asOfMonth = $month ?? 12;

        $scoped = GeneralLedger::where('upload_id', $upload->id)
            ->where('ledger_month', '<=', $asOfMonth);

        // Allotment comes from the NCA / receipts rows, not from transactions.
        $allotted = (float) (clone $scoped)
            ->where('row_type', 'allotment_header')
            ->sum('receipts');

        // Disbursed = NET, not gross. Gross includes withholding tax that
        // never actually leaves DOST's account via ADA/check - NET is what
        // was genuinely paid out. Confirmed 2026-07-30 (was gross_amount).
        $disbursed = (float) (clone $scoped)
            ->transactions()
            ->disbursed()
            ->sum('net_amount');

        return [
            'allotted'    => $allotted,
            'disbursed'   => $disbursed,
            'balance'     => $allotted - $disbursed,
            'utilization' => $allotted > 0 ? round($disbursed / $allotted * 100, 2) : 0,
        ];
    }

    /**
     * Per-month figures for the trend chart - the plain {MONTH} block, not
     * the cumulative AS-OF-{MONTH} one used by stats(). Two grouped queries
     * instead of 24 scoped ones.
     */
    private function monthlyTrend(?Upload $upload): array
    {
        if (! $upload || ! $upload->hasRows()) {
            return [];
        }

        $allottedByMonth = GeneralLedger::where('upload_id', $upload->id)
            ->where('row_type', 'allotment_header')
            ->selectRaw('ledger_month, SUM(receipts) as total')
            ->groupBy('ledger_month')
            ->pluck('total', 'ledger_month');

        $disbursedByMonth = GeneralLedger::where('upload_id', $upload->id)
            ->transactions()
            ->disbursed()
            ->selectRaw('ledger_month, SUM(net_amount) as total')
            ->groupBy('ledger_month')
            ->pluck('total', 'ledger_month');

        return collect(range(1, 12))->map(fn ($m) => [
            'month'     => $m,
            'allotted'  => (float) ($allottedByMonth[$m] ?? 0),
            'disbursed' => (float) ($disbursedByMonth[$m] ?? 0),
        ])->all();
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