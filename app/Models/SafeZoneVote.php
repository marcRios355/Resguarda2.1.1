<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafeZoneVote extends Model
{
    protected $fillable = [
        'safe_zone_id',
        'user_id',
        'vote',
    ];

    /** @return BelongsTo<SafeZone, $this> */
    public function safeZone(): BelongsTo
    {
        return $this->belongsTo(SafeZone::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
