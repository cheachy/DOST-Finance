<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'transaction_id', 'old_values', 'new_values'])]

class auditLog extends Model
{
    public const UPDATED_AT = null;

    protected $casts = [
      'old_values' => 'array',
      'new_values' => 'array',  
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function transaction(){
        return $this->belongsTo(Transaction::class);
    }
}
