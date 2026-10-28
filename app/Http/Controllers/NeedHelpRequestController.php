<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\NeedHelpRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class NeedHelpRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $districts = District::select('id', 'name', 'slug', 'risk_level')->get();

        $hasRequestsTable = Schema::hasTable('need_help_requests');
        $requests = $hasRequestsTable
            ? NeedHelpRequest::query()
                ->select(['district_id', 'help_type', 'urgency_level', 'status', 'created_at'])
                ->with('district:id,name')
                ->latest()
                ->get()
                ->map(fn (NeedHelpRequest $request) => [
                    'district_name' => $request->district?->name,
                    'help_type' => $request->help_type,
                    'urgency_level' => $request->urgency_level,
                    'status_label' => $request->statusLabel,
                    'created_at' => $request->created_at?->format('Y-m-d H:i'),
                ])
                ->all()
            : [];

        $myRequests = $hasRequestsTable
            ? NeedHelpRequest::where('user_id', $request->user()->id)
                ->latest()
                ->get(['id', 'created_at'])
                ->map(fn (NeedHelpRequest $helpRequest) => [
                    'id' => $helpRequest->id,
                    'created_at' => $helpRequest->created_at?->format('Y-m-d H:i'),
                ])
                ->all()
            : [];

        return Inertia::render('help/Index', [
            'districts' => $districts->map(fn (District $district): array => [
                'id' => $district->id,
                'name' => $district->name,
                'slug' => $district->slug,
                'risk_level' => $district->risk_level,
            ])->all(),
            'requests' => $requests,
            'myRequests' => $myRequests,
            'sosContactCount' => $request->user()->emergencyContacts()->count(),
            'sosEmailContactCount' => Schema::hasColumn('emergency_contacts', 'email')
                ? $request->user()->emergencyContacts()
                    ->get(['email'])
                    ->filter(fn ($contact): bool => is_string($contact->email) && trim($contact->email) !== '')
                    ->count()
                : 0,
            'sosFeedback' => $request->session()->get('sosFeedback'),
            'sosAlert' => fn (): ?array => Schema::hasTable('sos_alerts')
                && Schema::hasTable('sos_alert_deliveries')
                ? $request->user()->sosAlerts()
                    ->withCount([
                        'deliveries',
                        'deliveries as accepted_count' => fn ($query) => $query->where('status', 'accepted'),
                        'deliveries as failed_count' => fn ($query) => $query->where('status', 'failed'),
                        'deliveries as uncertain_count' => fn ($query) => $query->where('status', 'uncertain'),
                        'deliveries as pending_count' => fn ($query) => $query->where('status', 'pending'),
                    ])
                    ->latest('id')
                    ->first()
                    ?->only([
                        'id',
                        'delivery_status',
                        'created_at',
                        'deliveries_count',
                        'accepted_count',
                        'failed_count',
                        'uncertain_count',
                        'pending_count',
                    ])
                : null,
        ]);
    }

    public function show(Request $request, NeedHelpRequest $needHelpRequest): Response
    {
        $user = $request->user();

        if (! $user || (! $user->isAdmin() && (int) $needHelpRequest->user_id !== $user->id)) {
            abort(403, 'No autorizado.');
        }

        $needHelpRequest->load('district');

        return Inertia::render('help/Show', [
            'helpRequest' => [
                'id' => $needHelpRequest->id,
                'district_name' => $needHelpRequest->district?->name,
                'help_type' => $needHelpRequest->help_type,
                'urgency_level' => $needHelpRequest->urgency_level,
                'status' => $needHelpRequest->status,
                'status_label' => $needHelpRequest->statusLabel,
                'description' => $needHelpRequest->description,
                'address_reference' => $needHelpRequest->address_reference,
                'latitude' => $needHelpRequest->latitude,
                'longitude' => $needHelpRequest->longitude,
                'created_at' => $needHelpRequest->created_at?->format('Y-m-d H:i'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('need_help_requests')) {
            return back()->with('status', 'La estructura de solicitudes de ayuda aún no está creada.');
        }

        $validated = $request->validate([
            'district_id' => ['required', 'exists:districts,id'],
            'help_type' => ['required', 'in:medical,rescue,evacuation,food,transport,other'],
            'description' => ['required', 'string', 'max:1000'],
            'urgency_level' => ['required', 'in:low,medium,high,critical'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'address_reference' => ['nullable', 'string', 'max:255'],
        ]);

        NeedHelpRequest::create([
            'user_id' => $request->user()->id,
            'district_id' => $validated['district_id'],
            'help_type' => $validated['help_type'],
            'description' => $validated['description'],
            'urgency_level' => $validated['urgency_level'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'address_reference' => $validated['address_reference'] ?? null,
            'status' => 'pending',
            'is_active' => true,
        ]);

        return redirect()->route('need-help.index')->with('status', 'Solicitud registrada. El equipo de apoyo revisará su petición.');
    }

    public function resolve(Request $request, NeedHelpRequest $needHelpRequest): RedirectResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'No autorizado.');
        }

        if (! Schema::hasTable('need_help_requests')) {
            return back()->with('status', 'La tabla de apoyo no está disponible en este momento.');
        }

        $request->validate([
            'status' => ['required', 'in:pending,in_progress,resolved'],
        ]);

        $needHelpRequest->status = $request->input('status');
        $needHelpRequest->is_active = $request->input('status') !== 'resolved';
        $needHelpRequest->save();

        return back()->with('status', 'Estado actualizado.');
    }
}
