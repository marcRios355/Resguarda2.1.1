<?php

namespace App\Models;

use Database\Factories\SosAlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SosAlert extends Model
{
    /** @use HasFactory<SosAlertFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'delivery_status',
        'idempotency_key',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<EmergencyContact, $this> */
    public function emergencyContacts(): BelongsToMany
    {
        return $this->belongsToMany(
            EmergencyContact::class,
            'sos_alert_emergency_contact',
            'sos_alert_id',
            'emergency_contact_id',
        );
    }

    /** @return HasMany<SosAlertDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(SosAlertDelivery::class);
    }

    public function refreshDeliveryStatus(): void
    {
        $counts = $this->deliveries()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $counts->sum();
        $accepted = (int) ($counts['accepted'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);
        $uncertain = (int) ($counts['uncertain'] ?? 0);
        $pending = $total - $accepted - $failed - $uncertain;

        $status = match (true) {
            $total === 0 => 'no_recipients',
            $pending > 0 => 'pending',
            $accepted === $total => 'accepted',
            $uncertain > 0 && $accepted > 0 => 'partially_uncertain',
            $uncertain > 0 => 'uncertain',
            $accepted > 0 => 'partially_accepted',
            default => 'failed',
        };

        $this->forceFill(['delivery_status' => $status])->save();
    }
}
