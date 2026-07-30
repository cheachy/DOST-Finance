<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SlTab extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    /** The tabs to actually show, in display order. */
    public static function active(): Collection
    {
        return static::where('is_active', true)->orderBy('display_order')->get();
    }
}
