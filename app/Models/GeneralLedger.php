<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The general ledger - the single source of truth.
 *
 * Subsidiary ledgers, their summaries, and the monthly RODs are derived from
 * these rows and are never stored.
 *
 * IMPORTANT: every read should be scoped to the live snapshot. The table holds
 * more than one import at a time, so an unscoped query returns duplicates.
 * Use ::current($year) rather than remembering to filter by hand.
 */
class GeneralLedger extends Model
{
    use HasFactory;

    /**
     * What one disbursed row contributes to the ROD, as SQL.
     *
     * NET plus the numeric-RC value - NOT net alone.
     *
     * A remittance row carries TWO amounts. The government share sits in NET,
     * and the personal/employee share sits in the RC column, which on these
     * rows holds an amount rather than a responsibility centre (this is why
     * the parser moves a numeric RC into extras.rc_numeric - see
     * config/ledger.php 'remittance'). Both halves leave the account on the
     * same ADA/check, so both belong in the ROD cell.
     *
     * Confirmed 2026-08-13 against MDS 101, e.g. source_row 95 (GSIS,
     * January): net 270,415.08 + rc 388,095.25 = 658,510.33, exactly the
     * figure in her AR column. Some remittances carry NO government share at
     * all (rows 92-94: net 0.00, whole amount in RC), so summing net alone
     * silently dropped them entirely and under-stated PS in every month.
     */
    public const ROD_AMOUNT_SQL = "(COALESCE(net_amount, 0) + COALESCE(NULLIF(extras->>'rc_numeric', '')::numeric, 0))";

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'tax_details' => 'array',
        'extras' => 'array',
        'raw_row' => 'array',
        'payment_date' => 'date',
        'created_at' => 'datetime',
        'gross_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'charging_breakdown' => 'decimal:2',
        'receipts' => 'decimal:2',
        'rod_actual' => 'array',
    ];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    /** Rows belonging to the live snapshot for a fiscal year. */
    public function scopeCurrent(Builder $q, int $year): Builder
    {
        return $q->whereIn('upload_id', function ($sub) use ($year) {
            $sub->select('id')
                ->from('uploads')
                ->where('fiscal_year', $year)
                ->where('is_current', true)
                ->where('status', 'done');
        });
    }

    /** Real transactions only - excludes dividers, headers, subtotals. */
    public function scopeTransactions(Builder $q): Builder
    {
        return $q->where('row_type', 'transaction');
    }

    /** Sheet order. A ledger without an explicit order is not a ledger. */
    public function scopeInSheetOrder(Builder $q): Builder
    {
        return $q->orderBy('source_row');
    }

    /**
     * Disbursed rows only.
     *
     * Payment mode (A = ADA, C = Check) is what gates the ROD: an obligation
     * that has not been paid contributes nothing, whatever its status or colour.
     */
    public function scopeDisbursed(Builder $q): Builder
    {
        return $q->whereIn('payment_mode', ['A', 'C']);
    }

    public function scopeForMonth(Builder $q, int $month): Builder
    {
        return $q->where('ledger_month', $month);
    }

    /**
     * Current-year allotment rows only.
     *
     * The charging code is NOT sufficient on its own. A prior-year obligation
     * that was not-yet-due last year and became due-and-demandable this one is
     * paid out of the PRIOR-year allotment even though it still carries a
     * current-year charging code (e.g. "SAA-SARAI"). The sheet records that
     * only as a legend fill colour, so the status flag has to override the
     * code. Verified 2026-08-13 against MDS 101: all 93 disbursed rows
     * carrying the flag were typed into her prior-year ROD columns and none
     * into the current-year ones, so this is a clean gate, not a heuristic.
     */
    public function scopeCurrentYearAllotment(Builder $q): Builder
    {
        $flags = (array) config('ledger.classify.prior_year_flags', []);

        if (! $flags) {
            return $q;
        }

        return $q->where(fn ($w) => $w
            ->whereNull('status_flag')
            ->orWhereNotIn('status_flag', $flags));
    }

    /** PHP-side twin of ROD_AMOUNT_SQL - keep the two in step. */
    public function rodAmount(): float
    {
        return (float) $this->net_amount + (float) ($this->extras['rc_numeric'] ?? 0);
    }

    public function isDisbursed(): bool
    {
        return in_array($this->payment_mode, ['A', 'C'], true);
    }

    public function obrNumber(): ?string
    {
        return $this->obr_prefix || $this->obr_no
            ? trim(($this->obr_prefix ?? '').'-'.($this->obr_no ?? ''), '-')
            : null;
    }

    public function dvNumber(): ?string
    {
        return $this->dv_prefix || $this->dv_no
            ? trim(($this->dv_prefix ?? '').'-'.($this->dv_no ?? ''), '-')
            : null;
    }
}
