<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
class Payee extends Model
{
    public function transactions() {
        return $this->hasMany(Transaction::class);
    }
}
