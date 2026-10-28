<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class District extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'risk_level',
        'latitude',
        'longitude',
        'geojson_bounds',
        'description',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'geojson_bounds' => 'array',
    ];

    public function safeZones(): HasMany
    {
        return $this->hasMany(SafeZone::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function communityMessages(): HasMany
    {
        return $this->hasMany(CommunityMessage::class);
    }

    public function citizenEvidences(): HasMany
    {
        return $this->hasMany(CitizenEvidence::class);
    }

    public function districtZones(): HasMany
    {
        return $this->hasMany(DistrictZone::class);
    }

    public function riskSnapshots(): HasMany
    {
        return $this->hasMany(DistrictRiskSnapshot::class);
    }

    public function needHelpRequests(): HasMany
    {
        return $this->hasMany(NeedHelpRequest::class);
    }

    public function getRiskBadgeAttribute(): array
    {
        return match ($this->risk_level) {
            'safe' => ['label' => 'Seguro', 'color' => '#10B981', 'icon' => 'check-circle'],
            'caution' => ['label' => 'Precaución', 'color' => '#F59E0B', 'icon' => 'alert-triangle'],
            'risk' => ['label' => 'En Riesgo', 'color' => '#F97316', 'icon' => 'alert-circle'],
            'high_risk' => ['label' => 'Riesgo Alto', 'color' => '#EF4444', 'icon' => 'shield-alert'],
            'critical' => ['label' => 'Riesgo Crítico', 'color' => '#8B5CF6', 'icon' => 'flame'],
            'clearing' => ['label' => 'En Despeje', 'color' => '#64748B', 'icon' => 'refresh-cw'],
            default => ['label' => 'Desconocido', 'color' => '#94A3B8', 'icon' => 'help-circle'],
        };
    }

    public function refreshRiskSnapshot(): ?DistrictRiskSnapshot
    {
        $hasReportsTable = Schema::hasTable('reports');
        $hasCitizenEvidencesTable = Schema::hasTable('citizen_evidences');
        $hasEvidenceVotesTable = Schema::hasTable('evidence_votes');
        $hasAlertsTable = Schema::hasTable('alerts');
        $hasSafeZonesTable = Schema::hasTable('safe_zones');
        $hasRiskSnapshotsTable = Schema::hasTable('district_risk_snapshots');

        if (! $hasReportsTable && ! $hasCitizenEvidencesTable && ! $hasEvidenceVotesTable && ! $hasAlertsTable && ! $hasSafeZonesTable) {
            return null;
        }

        $activeReports = $hasReportsTable ? $this->reports()->whereIn('status', ['pending', 'community_alert', 'confirmed'])->get() : collect();
        $severityPoints = $activeReports->sum(fn (Report $report): int => match ($report->severity) {
            'baja' => 1,
            'media' => 2,
            'alta' => 3,
            'critica' => 4,
            default => 0,
        });

        $factors = [
            'reports' => $activeReports->count(),
            'severity_points' => $severityPoints,
            'evidences' => $hasCitizenEvidencesTable ? (int) $this->citizenEvidences()->count() : 0,
            'confirmations' => $hasCitizenEvidencesTable && $hasEvidenceVotesTable ? (int) EvidenceVote::query()
                ->join('citizen_evidences', 'citizen_evidences.id', '=', 'evidence_votes.evidence_id')
                ->where('citizen_evidences.district_id', $this->id)
                ->where('evidence_votes.vote_type', 'confirm')
                ->count() : 0,
            'active_alerts' => $hasAlertsTable ? (int) $this->alerts()->where('is_active', true)->count() : 0,
            'affected_areas' => $hasReportsTable ? (int) $this->reports()->whereIn('incident_type', ['calle_bloqueada', 'zona_en_riesgo', 'desborde', 'inundacion', 'huaico'])->count() : 0,
            'safe_zones' => $hasSafeZonesTable ? (int) $this->safeZones()->count() : 0,
        ];

        $score = min(100, (int) round(
            ($factors['reports'] * 12)
            + ($factors['severity_points'] * 4)
            + ($factors['evidences'] * 6)
            + ($factors['confirmations'] * 2)
            + ($factors['active_alerts'] * 10)
            + ($factors['affected_areas'] * 8)
            + ($factors['safe_zones'] > 0 ? 5 : 0)
        ));

        $riskLevel = match (true) {
            $score >= 80 => 'critical',
            $score >= 65 => 'high_risk',
            $score >= 45 => 'risk',
            $score >= 25 => 'caution',
            default => 'safe',
        };

        $this->forceFill(['risk_level' => $riskLevel]);
        $this->save();

        if (! $hasRiskSnapshotsTable) {
            return null;
        }

        return $this->riskSnapshots()->create([
            'score' => $score,
            'level' => $riskLevel,
            'calculated_at' => now(),
            'factors' => $factors,
            'notes' => 'Cálculo inicial de riesgo basado en reportes, evidencia, alertas y zonas.',
        ]);
    }
}
