<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\District;
use App\Models\Report;
use App\Models\SafeZone;
use App\Services\LiveMapData;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    public function index(LiveMapData $live): Response
    {
        $hasRiskSnapshotsTable = Schema::hasTable('district_risk_snapshots');
        $hasAlertsTable = Schema::hasTable('alerts');
        $hasCitizenEvidencesTable = Schema::hasTable('citizen_evidences');
        $hasNeedHelpRequestsTable = Schema::hasTable('need_help_requests');

        $districts = District::query()
            ->orderBy('name')
            ->get()
            ->map(function (District $district) use ($hasRiskSnapshotsTable, $hasAlertsTable, $hasCitizenEvidencesTable, $hasNeedHelpRequestsTable): array {
                $snapshot = $hasRiskSnapshotsTable ? $district->riskSnapshots()->latest('calculated_at')->first() : null;

                return [
                    'id' => $district->id,
                    'name' => $district->name,
                    'slug' => $district->slug,
                    'risk' => $district->risk_level,
                    'lat' => (float) ($district->latitude ?? 0),
                    'lng' => (float) ($district->longitude ?? 0),
                    'summary' => $snapshot?->notes ?? 'Sin resumen disponible.',
                    'alerts' => $hasAlertsTable ? (int) $district->alerts()->where('is_active', true)->count() : 0,
                    'evidence' => $hasCitizenEvidencesTable ? (int) $district->citizenEvidences()->count() : 0,
                    'help' => $hasNeedHelpRequestsTable ? (int) $district->needHelpRequests()->whereIn('status', ['pending', 'in_progress'])->count() : 0,
                    'risk_score' => (int) ($snapshot?->score ?? 0),
                ];
            })
            ->all();

        $safeZones = SafeZone::query()
            ->with('district:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (SafeZone $zone): array => [
                'id' => $zone->id,
                'name' => $zone->name,
                'address' => $zone->address,
                'district_id' => $zone->district_id,
                'district_name' => $zone->district?->name,
                'latitude' => (float) $zone->latitude,
                'longitude' => (float) $zone->longitude,
                'status' => $zone->status,
                'status_label' => $zone->status_meta['label'],
                'status_color' => $zone->status_meta['color'],
                'is_verified' => (bool) $zone->is_verified,
            ])
            ->all();

        $activeAlerts = $hasAlertsTable
            ? Alert::query()
                ->with('district:id,name')
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (Alert $alert): array => [
                    'id' => $alert->id,
                    'title' => $alert->title,
                    'message' => $alert->message,
                    'severity' => $alert->severity,
                    'district_id' => $alert->district_id,
                    'district_name' => $alert->district?->name,
                ])
                ->all()
            : [];

        $reports = Report::query()
            ->where('status', '!=', 'rejected')
            ->latest()
            ->limit(500)
            ->get(['id', 'title', 'incident_type', 'severity', 'status', 'latitude', 'longitude'])
            ->map(fn (Report $report): array => [
                'id' => $report->id,
                'title' => $report->title,
                'incident_type' => $report->incident_type,
                'severity' => $report->severity,
                'latitude' => (float) $report->latitude,
                'longitude' => (float) $report->longitude,
                'url' => route('reports.show', $report),
            ])
            ->all();

        return Inertia::render('Map', [
            'districts' => $districts,
            'safeZones' => $safeZones,
            'activeAlerts' => $activeAlerts,
            'reports' => $reports,
            'crowds' => $live->crowdHotspots(),
            'trafficEnabled' => $live->trafficEnabled(),
            'updatedAt' => now()->toIso8601String(),
        ]);
    }
}
