<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\CitizenEvidence;
use App\Models\District;
use App\Models\NeedHelpRequest;
use App\Models\Report;
use App\Models\SafeZone;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'No autorizado.');
        }

        $recentReports = Report::with(['user', 'district'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Report $report) => [
                'id' => $report->id,
                'title' => $report->title,
                'status' => $report->status,
                'severity' => $report->severity,
                'district_name' => $report->district?->name,
                'user_name' => $report->user?->name,
                'created_at' => $report->created_at?->format('Y-m-d H:i'),
            ])
            ->all();

        return Inertia::render('admin/Index', [
            'stats' => [
                'users' => User::count(),
                'reports' => Report::count(),
                'activeAlerts' => Alert::where('is_active', true)->count(),
            ],
            'recentReports' => $recentReports,
        ]);
    }

    public function users(Request $request): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'No autorizado.');
        }

        $users = User::query()
            ->select('id', 'name', 'email', 'username', 'role', 'reliability_level', 'status', 'created_at')
            ->latest()
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'role' => $user->role,
                'reliability_level' => $user->reliability_level,
                'status' => $user->status,
                'created_at' => $user->created_at?->format('Y-m-d H:i'),
            ])
            ->all();

        return Inertia::render('admin/Users', [
            'users' => $users,
        ]);
    }

    public function reports(Request $request): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'No autorizado.');
        }

        $reports = Report::with(['user', 'district'])
            ->latest()
            ->get()
            ->map(fn (Report $report) => [
                'id' => $report->id,
                'title' => $report->title,
                'description' => $report->description,
                'incident_type' => $report->incident_type,
                'severity' => $report->severity,
                'status' => $report->status,
                'status_label' => $report->statusLabel,
                'district_name' => $report->district?->name,
                'user_name' => $report->user?->name,
                'created_at' => $report->created_at?->format('Y-m-d H:i'),
            ])
            ->all();

        return Inertia::render('admin/Reports', [
            'reports' => $reports,
        ]);
    }

    public function moderation(Request $request): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'No autorizado.');
        }

        $evidences = Schema::hasTable('citizen_evidences')
            ? CitizenEvidence::with(['user', 'district'])
                ->whereIn('status', ['pending', 'under_review'])
                ->latest()
                ->get()
                ->map(fn (CitizenEvidence $evidence) => [
                    'id' => $evidence->id,
                    'title' => $evidence->title,
                    'description' => $evidence->description,
                    'incident_type' => $evidence->incident_type,
                    'severity' => $evidence->severity,
                    'status' => $evidence->status,
                    'status_label' => $evidence->status_label,
                    'district_name' => $evidence->district?->name,
                    'user_name' => $evidence->user?->name,
                    'confidence' => $evidence->confidence_percentage,
                    'created_at' => $evidence->created_at?->format('Y-m-d H:i'),
                ])
                ->all()
            : [];

        $helpRequests = Schema::hasTable('need_help_requests')
            ? NeedHelpRequest::with(['user', 'district'])
                ->whereIn('status', ['pending', 'in_progress'])
                ->latest()
                ->get()
                ->map(fn (NeedHelpRequest $request) => [
                    'id' => $request->id,
                    'help_type' => $request->help_type,
                    'description' => $request->description,
                    'urgency_level' => $request->urgency_level,
                    'status' => $request->status,
                    'status_label' => $request->status_label,
                    'district_name' => $request->district?->name,
                    'user_name' => $request->user?->name,
                    'created_at' => $request->created_at?->format('Y-m-d H:i'),
                ])
                ->all()
            : [];

        return Inertia::render('admin/Moderation', [
            'evidences' => $evidences,
            'helpRequests' => $helpRequests,
        ]);
    }

    public function updateEvidenceStatus(Request $request, CitizenEvidence $evidence): RedirectResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'No autorizado.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:pending,under_review,confirmed,resolved,rejected'],
        ]);

        $evidence->status = $validated['status'];
        $evidence->save();

        return redirect()->route('admin.moderation')->with('status', 'Estado de la evidencia actualizado.');
    }

    public function updateHelpStatus(Request $request, NeedHelpRequest $needHelpRequest): RedirectResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'No autorizado.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:pending,in_progress,resolved'],
        ]);

        $needHelpRequest->status = $validated['status'];
        $needHelpRequest->is_active = $validated['status'] !== 'resolved';
        $needHelpRequest->save();

        return redirect()->route('admin.moderation')->with('status', 'Estado de la solicitud de ayuda actualizado.');
    }

    public function alerts(Request $request): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'No autorizado.');
        }

        $alerts = Alert::with(['creator', 'district'])
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
                'is_active' => (bool) $alert->is_active,
                'expires_at' => $alert->expires_at?->format('Y-m-d H:i'),
            ])
            ->all();

        return Inertia::render('admin/Alerts', [
            'alerts' => $alerts,
        ]);
    }

    public function districts(Request $request): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'No autorizado.');
        }

        $districts = District::query()
            ->select('id', 'name', 'slug', 'risk_level', 'description', 'created_at')
            ->latest()
            ->get()
            ->map(fn (District $district) => [
                'id' => $district->id,
                'name' => $district->name,
                'slug' => $district->slug,
                'risk_level' => $district->risk_level,
                'description' => $district->description,
                'created_at' => $district->created_at?->format('Y-m-d H:i'),
            ])
            ->all();

        return Inertia::render('admin/Districts', [
            'districts' => $districts,
        ]);
    }

    public function safeZones(Request $request): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'No autorizado.');
        }

        $safeZones = SafeZone::with('district')
            ->latest()
            ->get()
            ->map(fn (SafeZone $zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'address' => $zone->address,
                'district_name' => $zone->district?->name,
                'capacity' => $zone->capacity,
                'current_occupancy' => $zone->current_occupancy,
                'status' => $zone->status,
                'is_verified' => (bool) $zone->is_verified,
                'created_at' => $zone->created_at?->format('Y-m-d H:i'),
            ])
            ->all();

        return Inertia::render('admin/SafeZones', [
            'safeZones' => $safeZones,
        ]);
    }

    public function updateReportStatus(Request $request, Report $report): RedirectResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'No autorizado.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:pending,community_alert,confirmed,rejected,resolved'],
        ]);

        $report->status = $validated['status'];
        $report->save();

        return redirect()->route('admin.reports')->with('status', 'Estado del reporte actualizado.');
    }
}
