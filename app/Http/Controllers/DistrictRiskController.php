<?php

namespace App\Http\Controllers;

use App\Models\District;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DistrictRiskController extends Controller
{
    public function index(): Response
    {
        $hasRiskSnapshotsTable = Schema::hasTable('district_risk_snapshots');

        $districts = District::query()
            ->get()
            ->map(function (District $district) use ($hasRiskSnapshotsTable): array {
                $snapshot = $hasRiskSnapshotsTable ? $district->riskSnapshots()->latest('calculated_at')->first() : null;

                return [
                    'id' => $district->id,
                    'name' => $district->name,
                    'risk_level' => $district->risk_level,
                    'risk_score' => $snapshot?->score ?? 0,
                    'summary' => $snapshot?->notes ?? 'Sin resumen disponible.',
                    'history' => $hasRiskSnapshotsTable ? $district->riskSnapshots()->latest('calculated_at')->limit(12)->get()->map(fn ($snapshot) => [
                        'label' => $snapshot->calculated_at?->format('H:i'),
                        'level' => $snapshot->level,
                        'score' => $snapshot->score,
                    ])->all() : [],
                ];
            })
            ->all();

        return Inertia::render('districts/Risk', [
            'districts' => $districts,
        ]);
    }

    public function show(District $district): Response
    {
        $hasRiskSnapshotsTable = Schema::hasTable('district_risk_snapshots');
        $snapshot = $hasRiskSnapshotsTable ? $district->riskSnapshots()->latest('calculated_at')->first() : null;

        return Inertia::render('districts/RiskDetail', [
            'district' => [
                'id' => $district->id,
                'name' => $district->name,
                'risk_level' => $district->risk_level,
                'risk_score' => $snapshot?->score ?? 0,
                'summary' => $snapshot?->notes ?? 'Sin resumen disponible.',
                'factors' => $snapshot?->factors ?? [],
                'history' => $hasRiskSnapshotsTable ? $district->riskSnapshots()->latest('calculated_at')->limit(12)->get()->map(fn ($snapshot) => [
                    'label' => $snapshot->calculated_at?->format('H:i'),
                    'level' => $snapshot->level,
                    'score' => $snapshot->score,
                ])->all() : [],
            ],
        ]);
    }
}
