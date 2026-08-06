<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per notable event (import, sign-in, export).
 *
 * Permanent: append-only audit trail, never edited.
 */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** Record an event. */
    public static function record(string $type, string $level, string $title, ?string $description = null, ?int $userId = null): self
    {
        return static::create([
            'type' => $type,
            'level' => $level,
            'title' => $title,
            'description' => $description,
            'user_id' => $userId,
        ]);
    }
}
