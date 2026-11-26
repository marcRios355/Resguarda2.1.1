<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SafeZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'district_id',
        'user_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'capacity',
        'current_occupancy',
        'status',
        'services',
        'image_path',
        'notes',
        'is_verified',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'capacity' => 'integer',
        'current_occupancy' => 'integer',
        'services' => 'array',
        'is_verified' => 'boolean',
    ];

    /** @return BelongsTo<District, $this> */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<SafeZoneVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(SafeZoneVote::class);
    }

    public function getOccupancyPercentageAttribute(): int
    {
        if ($this->capacity <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->current_occupancy / $this->capacity) * 100));
    }

    public function getStatusMetaAttribute(): array
    {
        return match ($this->status) {
            'available' => ['label' => 'Disponible', 'color' => '#10B981', 'tone' => 'emerald'],
            'limited' => ['label' => 'Capacidad limitada', 'color' => '#F59E0B', 'tone' => 'amber'],
            'almost_full' => ['label' => 'Casi llena', 'color' => '#F97316', 'tone' => 'orange'],
            'saturated' => ['label' => 'Saturada', 'color' => '#EF4444', 'tone' => 'red'],
            'unavailable' => ['label' => 'No disponible', 'color' => '#64748B', 'tone' => 'slate'],
            default => ['label' => 'Sin estado', 'color' => '#94A3B8', 'tone' => 'neutral'],
        };
    }
}
