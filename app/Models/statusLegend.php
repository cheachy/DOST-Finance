<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['status_code', 'label', 'fill_color_hex'])]
class statusLegend extends Model
{
    protected $table = 'status_legend';

    // transactions.w references status_legend.status_code, NOT
    // status_legend.id so both sides of this relationship need
    // explicit keys rather than Laravel's default id/foreign-key guess.
    public function transactions() {
        return $this->hasMany(Transaction::class, 'w', 'status_code');
    }
}
