<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class CitizenEvidence extends Model
{
    use HasFactory;

    protected $table = 'citizen_evidences';

    protected $fillable = [
        'user_id',
        'district_id',
        'district_zone_id',
        'incident_type',
        'title',
        'description',
        'latitude',
        'longitude',
        'address_reference',
        'photo_path',
        'severity',
        'status',
        'is_still_happening',
        'confirmation_count',
        'false_report_count',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_still_happening' => 'boolean',
        'confirmation_count' => 'integer',
        'false_report_count' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<District, $this> */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /** @return BelongsTo<DistrictZone, $this> */
    public function districtZone(): BelongsTo
    {
        return $this->belongsTo(DistrictZone::class);
    }

    /** @return HasMany<EvidenceVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(EvidenceVote::class, 'evidence_id');
    }

    /** @return HasMany<EvidenceReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(EvidenceReport::class, 'evidence_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pendiente',
            'under_review' => 'En revisión',
            'confirmed' => 'Confirmada',
            'resolved' => 'Resuelta',
            'rejected' => 'Rechazada',
            default => 'Sin estado',
        };
    }

    public function updateCounters(): void
    {
        if (! Schema::hasTable('evidence_votes')) {
            return;
        }

        $this->confirmation_count = $this->votes()->where('vote_type', 'confirm')->count();
        $this->false_report_count = $this->votes()->whereIn('vote_type', ['mismatch', 'outdated'])->count();
        $this->save();
    }

    public function updateStatusFromVotes(): void
    {
        if (! Schema::hasTable('evidence_votes')) {
            return;
        }

        $this->refresh();
        $this->updateCounters();

        if ($this->false_report_count >= 4) {
            $this->status = 'under_review';
        } elseif ($this->confirmation_count >= 3) {
            $this->status = 'confirmed';
        } elseif ($this->is_still_happening === false) {
            $this->status = 'resolved';
        } else {
            $this->status = 'pending';
        }

        $this->save();
        $this->district?->refreshRiskSnapshot();
    }

    public function getConfidencePercentageAttribute(): int
    {
        $total = max(1, $this->confirmation_count + $this->false_report_count);

        return (int) round(($this->confirmation_count / $total) * 100);
    }
}
