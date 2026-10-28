<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(): Response
    {
        $alerts = Alert::with('district', 'creator')
            ->where('is_active', true)
            ->latest()
            ->get()
            ->map(fn (Alert $alert) => [
                'id' => $alert->id,
                'title' => $alert->title,
                'message' => $alert->message,
                'type' => $alert->type,
                'severity' => $alert->severity,
                'district_name' => $alert->district?->name,
                'created_by' => $alert->creator?->name,
                'latitude' => (float) $alert->latitude,
                'longitude' => (float) $alert->longitude,
                'radius_meters' => $alert->radius_meters,
                'is_active' => (bool) $alert->is_active,
                'expires_at' => $alert->expires_at?->toDateTimeString(),
            ])
            ->all();

        return Inertia::render('alerts/Index', [
            'alerts' => $alerts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'district_id' => ['nullable', 'exists:districts,id'],
            'type' => ['required', 'string'],
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:1000'],
            'severity' => ['required', 'in:info,caution,danger,critical'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'radius_meters' => ['nullable', 'integer', 'min:50'],
        ]);

        Alert::create([
            'created_by' => $request->user()->id,
            'district_id' => $validated['district_id'] ?? null,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'message' => $validated['message'],
            'severity' => $validated['severity'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'radius_meters' => $validated['radius_meters'] ?? 300,
            'is_active' => true,
            'expires_at' => now()->addHours(12),
        ]);

        return redirect()->route('alerts.index')->with('status', 'Alerta activada correctamente.');
    }
}
