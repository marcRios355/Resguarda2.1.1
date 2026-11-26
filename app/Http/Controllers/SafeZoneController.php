<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\SafeZone;
use App\Models\SafeZoneVote;
use App\Services\DistrictLocator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SafeZoneController extends Controller
{
    /**
     * Servicios que un ciudadano puede indicar al publicar una zona segura.
     *
     * @var list<string>
     */
    public const SERVICES = ['agua', 'luz', 'banos', 'primeros_auxilios', 'alimentos', 'internet', 'seguridad'];

    /**
     * @var list<string>
     */
    public const STATUSES = ['available', 'limited', 'almost_full', 'saturated', 'unavailable'];

    public function index(Request $request): Response
    {
        $zones = $this->withVotes(SafeZone::query())
            ->orderBy('name')
            ->get()
            ->map(fn (SafeZone $zone): array => $this->present($zone))
            ->values()
            ->all();

        return Inertia::render('safe-zones/Index', [
            'zones' => $zones,
            'districts' => $this->districtOptions(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function show(SafeZone $safeZone): Response
    {
        $safeZone = $this->withVotes(SafeZone::query())->findOrFail($safeZone->id);

        return Inertia::render('safe-zones/Index', [
            'zones' => [$this->present($safeZone)],
            'districts' => $this->districtOptions(),
            'selectedZoneId' => $safeZone->id,
        ]);
    }

    /**
     * Registra una zona segura publicada por un ciudadano desde su ubicación.
     * Queda marcada como no verificada hasta que la revise un administrador.
     */
    public function store(Request $request, DistrictLocator $locator): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'district_id' => ['required', 'exists:districts,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'current_occupancy' => ['required', 'integer', 'min:0', 'lte:capacity'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'services' => ['nullable', 'array'],
            'services.*' => ['string', Rule::in(self::SERVICES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'current_occupancy.lte' => 'La ocupación no puede superar la capacidad.',
        ]);

        $location = $locator->locate((float) $validated['latitude'], (float) $validated['longitude']);

        if ($location['method'] === 'boundary' && $location['district']?->id !== (int) $validated['district_id']) {
            throw ValidationException::withMessages([
                'district_id' => 'La ubicación indicada pertenece al distrito '.$location['district']?->name.'. Corrige el distrito.',
            ]);
        }

        $zone = SafeZone::create([
            ...$validated,
            'services' => array_values(array_unique($validated['services'] ?? [])),
            'user_id' => $request->user()->id,
            'is_verified' => false,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zona segura publicada. Se mostrará como no verificada hasta su revisión.']);

        return redirect()->route('safe-zones.show', $zone);
    }

    /**
     * Registra o cambia el voto de la persona sobre si la zona es confiable.
     * Los totales se muestran a todos los usuarios.
     */
    public function vote(Request $request, SafeZone $safeZone): RedirectResponse
    {
        $validated = $request->validate([
            'vote' => ['required', Rule::in(['reliable', 'unreliable'])],
        ]);

        SafeZoneVote::updateOrCreate(
            ['safe_zone_id' => $safeZone->id, 'user_id' => $request->user()->id],
            ['vote' => $validated['vote']],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $validated['vote'] === 'reliable' ? 'Marcaste esta zona como confiable.' : 'Marcaste esta zona como no confiable.',
        ]);

        return back();
    }

    /**
     * @param  Builder<SafeZone>  $query
     * @return Builder<SafeZone>
     */
    private function withVotes(Builder $query): Builder
    {
        return $query
            ->with(['district', 'user:id,name', 'votes' => fn ($votes) => $votes->where('user_id', Auth::id())])
            ->withCount([
                'votes as reliable_votes_count' => fn ($votes) => $votes->where('vote', 'reliable'),
                'votes as unreliable_votes_count' => fn ($votes) => $votes->where('vote', 'unreliable'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SafeZone $zone): array
    {
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
            'published_by' => $zone->user?->name,
            'reliable_votes' => (int) ($zone->reliable_votes_count ?? 0),
            'unreliable_votes' => (int) ($zone->unreliable_votes_count ?? 0),
            'my_vote' => $zone->relationLoaded('votes') ? $zone->votes->first()?->vote : null,
            'updated_at' => $zone->updated_at?->toDateTimeString(),
            'distance_km' => null,
        ];
    }

    /**
     * @return list<array{id: int, name: string, latitude: float, longitude: float}>
     */
    private function districtOptions(): array
    {
        return District::query()
            ->orderBy('name')
            ->get(['id', 'name', 'latitude', 'longitude'])
            ->map(fn (District $district): array => [
                'id' => $district->id,
                'name' => $district->name,
                'latitude' => (float) $district->latitude,
                'longitude' => (float) $district->longitude,
            ])
            ->all();
    }
}
