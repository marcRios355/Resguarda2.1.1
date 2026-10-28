<?php

namespace App\Http\Controllers;

use App\Services\DistrictLocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Identifica el distrito y las zonas seguras cercanas a un punto. Las
     * coordenadas recibidas no se guardan.
     */
    public function lookup(Request $request, DistrictLocator $locator): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $location = $locator->locate($latitude, $longitude);

        return response()->json([
            'district' => $location['district'] !== null ? [
                'id' => $location['district']->id,
                'name' => $location['district']->name,
                'slug' => $location['district']->slug,
            ] : null,
            'method' => $location['method'],
            'approximate' => $location['approximate'],
            'distance_km' => $location['distance_km'],
            'safe_zones' => $locator->nearestSafeZones($latitude, $longitude, 5)
                ->map(fn (array $item): array => [
                    'id' => $item['zone']->id,
                    'name' => $item['zone']->name,
                    'address' => $item['zone']->address,
                    'district_name' => $item['zone']->district?->name,
                    'latitude' => (float) $item['zone']->latitude,
                    'longitude' => (float) $item['zone']->longitude,
                    'status' => $item['zone']->status,
                    'status_label' => $item['zone']->status_meta['label'],
                    'capacity' => $item['zone']->capacity,
                    'current_occupancy' => $item['zone']->current_occupancy,
                    'is_verified' => (bool) $item['zone']->is_verified,
                    'distance_km' => $item['distance_km'],
                ])
                ->all(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
