<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'label',
])]
class TaxType extends Model
{
    public function transactionTaxLines()
    {
        return $this->hasMany(TransactionTaxLine::class);
    }
}