<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'fiscal_year',
    'category',            // 'PS' | 'MOOE' | 'CO'
    'allotment_year_type', // 'current' | 'prior'
    'amount',
])]
class Allotment extends Model
{
    protected $casts = [
        'fiscal_year' => 'integer',
        'amount' => 'decimal:2',
    ];
}