<?php

namespace App\Http\Controllers;

use App\Models\District;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    public function index(): Response
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

        return Inertia::render('Map', [
            'districts' => $districts,
        ]);
    }
}
