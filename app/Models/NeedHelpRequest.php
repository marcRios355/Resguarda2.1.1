<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NeedHelpRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'district_id',
        'latitude',
        'longitude',
        'address_reference',
        'help_type',
        'description',
        'urgency_level',
        'status',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pendiente',
            'in_progress' => 'En atención',
            'resolved' => 'Atendido',
            default => 'Sin estado',
        };
    }
}
