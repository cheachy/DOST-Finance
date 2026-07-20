<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'account_id',
    'period',
    'file_path',
    'generated_by',
    'generated_at',
])]
class reportExport extends Model
{
    protected $casts = [
        'generated_at' => 'datetime',
    ];
 
    public function account() {
        return $this->belongsTo(Account::class);
    }
 
    public function generatedBy() {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
