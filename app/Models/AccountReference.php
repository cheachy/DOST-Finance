<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Routing table for charging/RC codes, seeded from the workbook's own `Ref`
 * sheet (see AccountReferenceSeeder). Which charging codes route to which SL
 * tab is a DATA decision (`php artisan ledger:sl-tab`), not hardcoded here —
 * see the `sl_tabs` table / SlTab model. A row with `canonical_code` set is
 * an alias (`php artisan ledger:alias`) and always mirrors its canonical
 * row's scope.
 */
class AccountReference extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_prior_year' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** Charging codes routed to a given SL tab. */
    public function scopeForSlTab(Builder $q, string $tab): Builder
    {
        return $q->where('ref_type', 'charging')
            ->where('sl_tab', $tab)
            ->where('is_active', true);
    }
}
