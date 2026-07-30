<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Routing table for charging/RC codes, seeded from the workbook's own `Ref`
 * sheet (see AccountReferenceSeeder). Phase 1 only routes PS / MOOE / GIA;
 * everything else is known but out of scope (sl_tab null, is_active false).
 */
class AccountReference extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_prior_year' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** Charging codes routed to a given SL tab (PS | MOOE | GIA). */
    public function scopeForSlTab(Builder $q, string $tab): Builder
    {
        return $q->where('ref_type', 'charging')
            ->where('sl_tab', $tab)
            ->where('is_active', true);
    }
}
