<?php

namespace App\Http\Controllers;

use App\Models\SafeZone;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SafeZoneController extends Controller
{
    public function index(Request $request): Response
    {
        $zones = SafeZone::with('district')
            ->orderBy('name')
            ->get()
            ->map(function (SafeZone $zone): array {
                $statusMeta = $zone->statusMeta;

                return [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'address' => $zone->address,
                    'district_id' => $zone->district_id,
                    'district_name' => $zone->district?->name,
                    'latitude' => (float) $zone->latitude,
                    'longitude' => (float) $zone->longitude,
                    'capacity' => $zone->capacity,
                    'current_occupancy' => $zone->current_occupancy,
                    'occupancy_percentage' => $zone->occupancy_percentage,
                    'status' => $zone->status,
                    'status_label' => $statusMeta['label'],
                    'status_color' => $statusMeta['color'],
                    'services' => $zone->services ?? [],
                    'image_path' => $zone->image_path,
                    'notes' => $zone->notes,
                    'is_verified' => (bool) $zone->is_verified,
                    'updated_at' => $zone->updated_at?->toDateTimeString(),
                    'distance_km' => null,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('safe-zones/Index', [
            'zones' => $zones,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function show(SafeZone $safeZone): Response
    {
        $safeZone->load('district');

        return Inertia::render('safe-zones/Index', [
            'zones' => [
                [
                    'id' => $safeZone->id,
                    'name' => $safeZone->name,
                    'address' => $safeZone->address,
                    'district_id' => $safeZone->district_id,
                    'district_name' => $safeZone->district?->name,
                    'latitude' => (float) $safeZone->latitude,
                    'longitude' => (float) $safeZone->longitude,
                    'capacity' => $safeZone->capacity,
                    'current_occupancy' => $safeZone->current_occupancy,
                    'occupancy_percentage' => $safeZone->occupancy_percentage,
                    'status' => $safeZone->status,
                    'status_label' => $safeZone->statusMeta['label'],
                    'status_color' => $safeZone->statusMeta['color'],
                    'services' => $safeZone->services ?? [],
                    'image_path' => $safeZone->image_path,
                    'notes' => $safeZone->notes,
                    'is_verified' => (bool) $safeZone->is_verified,
                    'updated_at' => $safeZone->updated_at?->toDateTimeString(),
                    'distance_km' => null,
                ],
            ],
            'selectedZoneId' => $safeZone->id,
        ]);
    }
}
