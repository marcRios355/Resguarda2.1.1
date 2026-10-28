<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'district_id',
        'incident_type',
        'title',
        'description',
        'latitude',
        'longitude',
        'address_reference',
        'photo_path',
        'severity',
        'status',
        'confirmations_count',
        'rejections_count',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'confirmations_count' => 'integer',
        'rejections_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(ReportConfirmation::class);
    }

    public function updateCounters(): void
    {
        $this->confirmations_count = $this->confirmations()->where('vote', 'confirm')->count();
        $this->rejections_count = $this->confirmations()->where('vote', 'reject')->count();
        $this->save();
    }

    public function updateStatusFromVotes(): void
    {
        $this->refresh();
        $this->updateCounters();

        if ($this->rejections_count >= 5) {
            $this->status = 'rejected';
        } elseif ($this->confirmations_count >= 5) {
            $this->status = 'confirmed';
        } elseif ($this->confirmations_count >= 3) {
            $this->status = 'community_alert';
        } else {
            $this->status = 'pending';
        }

        $this->save();

        if ($this->status === 'confirmed') {
            $this->createActiveAlert();
        }
    }

    public function createActiveAlert(): void
    {
        $alert = Alert::query()
            ->where('district_id', $this->district_id)
            ->where('type', $this->incident_type)
            ->where('title', $this->title)
            ->where('is_active', true)
            ->first();

        if ($alert) {
            return;
        }

        Alert::create([
            'created_by' => $this->user_id,
            'district_id' => $this->district_id,
            'type' => match ($this->incident_type) {
                'sismo' => 'sismo',
                'inundacion' => 'inundacion',
                'huaico' => 'huaico',
                'lluvia_intensa' => 'lluvia_intensa',
                'desborde' => 'desborde',
                'calle_bloqueada', 'zona_en_riesgo', 'personas_atrapadas', 'zona_despejada', 'zona_en_proceso_despeje', 'zona_segura', 'otro' => 'alerta_general',
                default => 'alerta_general',
            },
            'title' => $this->title,
            'message' => $this->description,
            'severity' => match ($this->severity) {
                'critica' => 'critical',
                'alta' => 'danger',
                'media' => 'caution',
                'baja' => 'info',
                default => 'info',
            },
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_meters' => 300,
            'is_active' => true,
            'expires_at' => Carbon::now()->addHours(12),
        ]);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pendiente',
            'community_alert' => 'Alerta comunitaria',
            'confirmed' => 'Confirmado',
            'rejected' => 'Rechazado',
            'resolved' => 'Resuelto',
            default => 'Sin estado',
        };
    }
}
