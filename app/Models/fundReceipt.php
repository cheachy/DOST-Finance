<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'date',
    'fiscal_year',
    'source',
    'reference_no',
    'amount',
    'import_id',
    'user_id',
])]
class fundReceipt extends Model
{
    protected $casts = [
        'date' => 'date',
        'fiscal_year' => 'integer',
        'amount' => 'decimal:2',
    ];
 
    public function excelImport() {
        return $this->belongsTo(ExcelImport::class, 'import_id');
    }
 
    public function user() {
        return $this->belongsTo(User::class);
    }
}
