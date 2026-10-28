<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\DistrictZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DistrictZoneController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'No autorizado.');
        }

        $districts = District::with('districtZones')
            ->get()
            ->map(fn (District $district) => [
                'id' => $district->id,
                'name' => $district->name,
                'risk_level' => $district->risk_level,
                'zones' => $district->districtZones
                    ->map(fn (DistrictZone $zone) => [
                        'id' => $zone->id,
                        'name' => $zone->name,
                        'zone_type' => $zone->zone_type,
                        'radius_meters' => $zone->radius_meters,
                        'latitude' => $zone->latitude,
                        'longitude' => $zone->longitude,
                    ])
                    ->all(),
            ])
            ->all();

        return Inertia::render('districts/Zones', [
            'districts' => $districts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'district_id' => ['required', 'exists:districts,id'],
            'name' => ['required', 'string', 'max:150'],
            'zone_type' => ['required', 'in:residential,flood_zone,blocked_street,critical_area,evacuation_route,other'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'integer', 'min:10', 'max:50000'],
        ]);

        $district = District::findOrFail($validated['district_id']);

        $district->districtZones()->create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'zone_type' => $validated['zone_type'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'radius_meters' => $validated['radius_meters'] ?? 500,
        ]);

        $district->refreshRiskSnapshot();

        return redirect()->route('district-zones.index')->with('status', 'Zona registrada correctamente.');
    }

    public function update(Request $request, DistrictZone $districtZone): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'zone_type' => ['required', 'in:residential,flood_zone,blocked_street,critical_area,evacuation_route,other'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'integer', 'min:10', 'max:50000'],
        ]);

        $districtZone->fill([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'zone_type' => $validated['zone_type'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'radius_meters' => $validated['radius_meters'] ?? $districtZone->radius_meters,
        ]);
        $districtZone->save();

        $districtZone->district?->refreshRiskSnapshot();

        return redirect()->route('district-zones.index')->with('status', 'Zona actualizada correctamente.');
    }

    public function destroy(DistrictZone $districtZone): RedirectResponse
    {
        $district = $districtZone->district;
        $districtZone->delete();
        $district?->refreshRiskSnapshot();

        return redirect()->route('district-zones.index')->with('status', 'Zona eliminada correctamente.');
    }
}
