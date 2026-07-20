<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'code',
    'charging_value',
    'category',      // 'PS' | 'MOOE' | 'CO' | null
    'fund_status',
    'sub_project',
])]
class Account extends Model
{
    public function transactions() {
        return $this->hasMany(Transaction::class);
    }
}
