<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Report;
use App\Models\ReportConfirmation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $reports = Report::with(['user', 'district', 'confirmations'])
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

        $districts = District::select('id', 'name', 'slug', 'risk_level', 'latitude', 'longitude')->get();
        $selectedDistrict = $districts->first(fn (District $district): bool => $district->slug === $request->string('district')->toString());

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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'district_id' => ['required', 'exists:districts,id'],
            'incident_type' => ['required', 'string'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:1000'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'address_reference' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', 'in:baja,media,alta,critica'],
        ]);

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

        return redirect()->route('reports.index')->with('status', 'Reporte registrado y pendiente de validación comunitaria.');
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
