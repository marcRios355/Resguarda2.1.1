<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Report;
use App\Models\ReportConfirmation;
use App\Services\DistrictLocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Tipos de publicación permitidos, incluidas las zonas que el ciudadano
     * considera seguras.
     *
     * @var list<string>
     */
    public const INCIDENT_TYPES = [
        'inundacion',
        'huaico',
        'desborde',
        'calle_bloqueada',
        'zona_en_riesgo',
        'zona_segura',
        'personas_atrapadas',
        'zona_despejada',
        'zona_en_proceso_despeje',
        'otro',
    ];

    public function index(Request $request): Response
    {
        $districts = District::select('id', 'name', 'slug', 'risk_level', 'latitude', 'longitude')->get();
        $selectedDistrict = $districts->first(fn (District $district): bool => $district->slug === $request->string('district')->toString());

        $reports = Report::with(['user', 'district', 'confirmations'])
            ->when($selectedDistrict, fn ($query) => $query->where('district_id', $selectedDistrict->id))
            ->latest()
            ->get()
            ->map(function (Report $report): array {
                return [
                    'id' => $report->id,
                    'title' => $report->title,
                    'description' => $report->description,
                    'incident_type' => $report->incident_type,
                    'severity' => $report->severity,
                    'status' => $report->status,
                    'status_label' => $report->statusLabel,
                    'district_name' => $report->district?->name,
                    'user_name' => $report->user?->name,
                    'confirmations_count' => $report->confirmations_count,
                    'rejections_count' => $report->rejections_count,
                    'created_at' => $report->created_at?->format('Y-m-d H:i:s'),
                    'latitude' => (float) $report->latitude,
                    'longitude' => (float) $report->longitude,
                    'address_reference' => $report->address_reference,
                    'vote' => $report->confirmations()->where('user_id', Auth::id())->first()?->vote,
                ];
            })
            ->all();

        return Inertia::render('reports/Index', [
            'reports' => $reports,
            'districts' => $districts->map(fn (District $district): array => [
                'id' => $district->id,
                'name' => $district->name,
                'slug' => $district->slug,
                'risk_level' => $district->risk_level,
                'latitude' => (float) $district->latitude,
                'longitude' => (float) $district->longitude,
            ])->all(),
            'selected_district_id' => $selectedDistrict?->id,
            'filtered_district_name' => $selectedDistrict?->name,
        ]);
    }

    public function show(Report $report): Response
    {
        $report->load(['user:id,name', 'district:id,name']);

        return Inertia::render('reports/Show', [
            'report' => [
                'id' => $report->id,
                'title' => $report->title,
                'description' => $report->description,
                'incident_type' => $report->incident_type,
                'severity' => $report->severity,
                'status' => $report->status,
                'status_label' => $report->statusLabel,
                'district_name' => $report->district?->name,
                'user_name' => $report->user?->name,
                'confirmations_count' => $report->confirmations_count,
                'rejections_count' => $report->rejections_count,
                'created_at' => $report->created_at?->toIso8601String(),
                'latitude' => (float) $report->latitude,
                'longitude' => (float) $report->longitude,
                'address_reference' => $report->address_reference,
                'vote' => $report->confirmations()->where('user_id', Auth::id())->value('vote'),
            ],
        ]);
    }

    public function store(Request $request, DistrictLocator $locator): RedirectResponse
    {
        $validated = $request->validate([
            'district_id' => ['required', 'exists:districts,id'],
            'incident_type' => ['required', 'string', Rule::in(self::INCIDENT_TYPES)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:1000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address_reference' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', 'in:baja,media,alta,critica'],
        ]);

        $location = $locator->locate((float) $validated['latitude'], (float) $validated['longitude']);

        // Solo se bloquea cuando los límites distritales confirman otro distrito;
        // una estimación por cercanía no basta para rechazar la elección del usuario.
        if ($location['method'] === 'boundary' && $location['district']?->id !== (int) $validated['district_id']) {
            throw ValidationException::withMessages([
                'district_id' => 'La ubicación indicada pertenece al distrito '.$location['district']?->name.'. Corrige el distrito del reporte.',
            ]);
        }

        $report = Report::create([
            'user_id' => $request->user()->id,
            'district_id' => $validated['district_id'],
            'incident_type' => $validated['incident_type'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'address_reference' => $validated['address_reference'] ?? null,
            'severity' => $validated['severity'],
            'status' => 'pending',
            'confirmations_count' => 0,
            'rejections_count' => 0,
        ]);

        return redirect()->route('reports.show', $report)->with('status', 'Publicación registrada y pendiente de validación comunitaria.');
    }

    public function vote(Request $request, Report $report): RedirectResponse
    {
        $validated = $request->validate([
            'vote' => ['required', 'in:confirm,reject'],
        ]);

        $existing = ReportConfirmation::where('report_id', $report->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            return back()->with('status', 'Ya registraste tu opinión para este reporte.');
        }

        $confirmation = ReportConfirmation::create([
            'report_id' => $report->id,
            'user_id' => $request->user()->id,
            'vote' => $validated['vote'],
        ]);

        $report->updateCounters();
        $report->updateStatusFromVotes();

        return back()->with('status', $confirmation->vote === 'confirm'
            ? 'Confirmación registrada.'
            : 'Rechazo registrado.');
    }
}
