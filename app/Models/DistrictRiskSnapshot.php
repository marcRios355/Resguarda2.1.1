<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistrictRiskSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'district_id',
        'score',
        'level',
        'calculated_at',
        'factors',
        'notes',
    ];

    protected $casts = [
        'score' => 'integer',
        'calculated_at' => 'datetime',
        'factors' => 'array',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function getLevelLabelAttribute(): string
    {
        return match ($this->level) {
            'safe' => 'Seguro',
            'caution' => 'Precaución',
            'risk' => 'En riesgo',
            'high_risk' => 'Riesgo alto',
            'critical' => 'Riesgo crítico',
            'clearing' => 'En despeje',
            default => 'Sin nivel',
        };
    }
}
