<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'file_name', 
    'file_path', 
    'uploaded_by', 
    'uploaded_at', 
    'status', 
    'fiscal_year', 
    'notes'
])]
class excelImport extends Model
{
    protected $casts = [
        'uploaded_at' => 'date',
    ];

    public function uploadedBy(){
        return $this->belongsTo(User::class, 'uploaded_by');
    }
    public function transactions(){
        return $this->hasMany(Transaction::class, 'import_id');
    }
    public function fundReceipts(){
        return $this->hasMany(fundReceipt::class);
    }
    public static function latestCompleted() {
        return static::query()
            ->where('status', 'completed')
            ->orderByDesc('uploaded_at')
            ->first();
    }
}
