<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'transaction_id', 'old_values', 'new_values'])]

class auditLogs extends Model
{
    const UPDATED_AT = null;
}
