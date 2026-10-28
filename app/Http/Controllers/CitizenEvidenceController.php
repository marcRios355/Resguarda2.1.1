<?php

namespace App\Http\Controllers;

use App\Models\CitizenEvidence;
use App\Models\District;
use App\Models\EvidenceReport;
use App\Models\EvidenceVote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CitizenEvidenceController extends Controller
{
    public function index(Request $request): Response
    {
        $districts = District::query()
            ->select('id', 'name', 'slug', 'risk_level', 'latitude', 'longitude')
            ->orderBy('name')
            ->get();
        $defaultDistrictId = $districts->first()?->id;
        $selectedDistrict = $districts->first(fn (District $district): bool => $district->id === (int) $request->query('district_id', $defaultDistrictId));

        if (! Schema::hasTable('citizen_evidences')) {
            return Inertia::render('evidence/Index', [
                'districts' => $districts->map(fn (District $district): array => [
                    'id' => $district->id,
                    'name' => $district->name,
                    'slug' => $district->slug,
                    'risk_level' => $district->risk_level,
                    'latitude' => (float) $district->latitude,
                    'longitude' => (float) $district->longitude,
                ])->all(),
                'evidences' => [],
                'selected_district_id' => $selectedDistrict?->id,
            ]);
        }

        $hasEvidenceVotesTable = Schema::hasTable('evidence_votes');
        $evidences = CitizenEvidence::with(['user', 'district'])
            ->when($selectedDistrict, fn ($query) => $query->where('district_id', $selectedDistrict->id))
            ->latest()
            ->get();

        if ($hasEvidenceVotesTable) {
            $evidences->load([
                'votes' => fn ($query) => $query->where('user_id', $request->user()?->id),
            ]);
        }

        $evidences = $evidences->map(fn (CitizenEvidence $evidence) => [
            'id' => $evidence->id,
            'title' => $evidence->title,
            'description' => $evidence->description,
            'incident_type' => $evidence->incident_type,
            'severity' => $evidence->severity,
            'status' => $evidence->status,
            'status_label' => $evidence->statusLabel,
            'is_still_happening' => (bool) $evidence->is_still_happening,
            'district_name' => $evidence->district?->name,
            'user_name' => $evidence->user?->name,
            'photo_path' => $evidence->photo_path ? Storage::disk('public')->url($evidence->photo_path) : null,
            'latitude' => (float) ($evidence->latitude ?? 0),
            'longitude' => (float) ($evidence->longitude ?? 0),
            'address_reference' => $evidence->address_reference,
            'confirmation_count' => $evidence->confirmation_count,
            'false_report_count' => $evidence->false_report_count,
            'confidence_percentage' => $evidence->confidence_percentage,
            'created_at' => $evidence->created_at?->format('Y-m-d H:i'),
            'vote' => $hasEvidenceVotesTable ? $evidence->votes->first()?->vote_type : null,
        ])->all();

        return Inertia::render('evidence/Index', [
            'districts' => $districts->map(fn (District $district): array => [
                'id' => $district->id,
                'name' => $district->name,
                'slug' => $district->slug,
                'risk_level' => $district->risk_level,
                'latitude' => (float) $district->latitude,
                'longitude' => (float) $district->longitude,
            ])->all(),
            'evidences' => $evidences,
            'selected_district_id' => $selectedDistrict?->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('citizen_evidences')) {
            return back()->with('status', 'La base de datos de evidencias no está disponible aún.');
        }

        $validated = $request->validate([
            'district_id' => ['required', 'exists:districts,id'],
            'incident_type' => ['required', 'in:inundacion,huaico,desborde,calle_bloqueada,materiales_caidos,deslizamiento,sismo,zona_afectada,refugio_saturado,vialidad,otro'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address_reference' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', 'in:baja,media,alta,critica'],
            'is_still_happening' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('evidence', 'public');
        }

        $evidence = CitizenEvidence::create([
            'user_id' => $request->user()->id,
            'district_id' => $validated['district_id'],
            'incident_type' => $validated['incident_type'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'address_reference' => $validated['address_reference'] ?? null,
            'photo_path' => $photoPath,
            'severity' => $validated['severity'],
            'status' => 'pending',
            'is_still_happening' => (bool) ($validated['is_still_happening'] ?? true),
            'confirmation_count' => 0,
            'false_report_count' => 0,
        ]);

        $evidence->district?->refreshRiskSnapshot();

        return redirect()->route('citizen-evidence.index', ['district_id' => $evidence->district_id])->with('status', 'Evidencia registrada correctamente y enviada a revisión comunitaria.');
    }

    public function vote(Request $request, CitizenEvidence $evidence): RedirectResponse
    {
        if (! Schema::hasTable('evidence_votes')) {
            return back()->with('status', 'La funcionalidad de votos no está disponible aún.');
        }

        $validated = $request->validate([
            'vote_type' => ['required', 'in:confirm,mismatch,outdated'],
        ]);

        $existing = EvidenceVote::where('evidence_id', $evidence->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            return back()->with('status', 'Ya registraste tu opinión para esta evidencia.');
        }

        EvidenceVote::create([
            'evidence_id' => $evidence->id,
            'user_id' => $request->user()->id,
            'vote_type' => $validated['vote_type'],
        ]);

        $evidence->updateCounters();
        $evidence->updateStatusFromVotes();

        return back()->with('status', 'Tu validación fue registrada.');
    }

    public function report(Request $request, CitizenEvidence $evidence): RedirectResponse
    {
        if (! Schema::hasTable('evidence_reports')) {
            return back()->with('status', 'La funcionalidad de reportes no está disponible aún.');
        }

        $validated = $request->validate([
            'report_reason' => ['required', 'in:false_information,old_photo,incorrect_content,incorrect_location,inappropriate_content,spam'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        EvidenceReport::create([
            'evidence_id' => $evidence->id,
            'user_id' => $request->user()->id,
            'report_reason' => $validated['report_reason'],
            'notes' => $validated['notes'] ?? null,
            'review_status' => 'pending',
        ]);

        return back()->with('status', 'La evidencia fue reportada y será revisada por la administración.');
    }

    public function updateStatus(Request $request, CitizenEvidence $evidence): RedirectResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'No autorizado.');
        }

        if (! Schema::hasTable('citizen_evidences')) {
            return back()->with('status', 'La evidencia no está disponible en la base de datos actual.');
        }

        $request->validate([
            'status' => ['required', 'in:pending,under_review,confirmed,resolved,rejected'],
            'is_still_happening' => ['nullable', 'boolean'],
        ]);

        $evidence->status = $request->input('status');
        $evidence->is_still_happening = (bool) ($request->input('is_still_happening', $evidence->is_still_happening));
        $evidence->save();
        $evidence->district?->refreshRiskSnapshot();

        return back()->with('status', 'Estado actualizado.');
    }
}
