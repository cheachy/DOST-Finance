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
}
