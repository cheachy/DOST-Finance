<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'key',
    'value',
])]
class appSetting extends Model
{
    // The single dynamic reference point for "current vs prior" fiscal
    // year comparisons across the whole app (Monthly ROD, allotments,
    // etc.). Never hardcode a year in a query — always read it from here.
    public static function activeFiscalYear(): int {
        $value = static::query()
            ->where('key', 'active_fiscal_year')
            ->value('value');
            
        return $value ? (int) $value : (int) date('Y');
    }
}
