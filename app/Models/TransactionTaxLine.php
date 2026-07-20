<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'transaction_id',
    'tax_type_id',
    'amount',
])]
class TransactionTaxLine extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function taxType()
    {
        return $this->belongsTo(TaxType::class);
    }
}