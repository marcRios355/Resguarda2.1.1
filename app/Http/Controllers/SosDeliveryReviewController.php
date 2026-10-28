<?php

namespace App\Http\Controllers;

use App\Models\SosAlertDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SosDeliveryReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $schemaReady = $this->reviewSchemaReady();
        $deliveries = $schemaReady
            ? SosAlertDelivery::query()
                ->with(['alert.user'])
                ->where('status', 'uncertain')
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (SosAlertDelivery $delivery): array => [
                    'id' => $delivery->id,
                    'alert_id' => $delivery->sos_alert_id,
                    'owner_name' => $delivery->alert->user->name,
                    'recipient_email' => $delivery->recipient_email,
                    'failure_code' => $delivery->failure_code,
                    'attempt_count' => $delivery->attempt_count,
                    'first_attempted_at' => $delivery->first_attempted_at?->format('Y-m-d H:i'),
                ])
                ->all()
            : [];

        return Inertia::render('admin/SosDeliveryReviews', [
            'schemaReady' => $schemaReady,
            'deliveries' => $deliveries,
            'status' => $request->session()->get('status'),
            'error' => $request->session()->get('error'),
        ]);
    }

    public function update(Request $request, SosAlertDelivery $sosAlertDelivery): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if (! $this->reviewSchemaReady()) {
            return back()->with('error', 'La revisión SOS no está disponible hasta aplicar las migraciones pendientes.');
        }

        $validated = $request->validate([
            'outcome' => ['required', 'in:accepted,not_accepted'],
            'provider_message_id' => ['required_if:outcome,accepted', 'nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $sosAlertDelivery, $validated): void {
            $delivery = SosAlertDelivery::query()
                ->lockForUpdate()
                ->findOrFail($sosAlertDelivery->id);

            if ($delivery->status !== 'uncertain') {
                abort(409, 'Esta entrega ya fue revisada o su estado cambió.');
            }

            $accepted = $validated['outcome'] === 'accepted';
            $delivery->forceFill([
                'status' => $accepted ? 'accepted' : 'failed',
                'accepted_at' => $accepted ? now() : null,
                'provider_message_id' => $accepted ? $validated['provider_message_id'] : null,
                'failure_code' => $accepted ? null : 'manual_confirmed_not_accepted',
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
                'review_outcome' => $validated['outcome'],
            ])->save();

            $delivery->alert->refreshDeliveryStatus();
        });

        return to_route('admin.sos-deliveries.index')
            ->with('status', 'La revisión quedó registrada. No se volvió a enviar ningún correo.');
    }

    private function authorizeAdmin(Request $request): void
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'No autorizado.');
        }
    }

    private function reviewSchemaReady(): bool
    {
        if (! Schema::hasTable('sos_alert_deliveries')
            || ! Schema::hasTable('sos_alerts')
            || ! Schema::hasTable('users')) {
            return false;
        }

        foreach ([
            'id',
            'sos_alert_id',
            'recipient_email',
            'status',
            'attempt_count',
            'first_attempted_at',
            'failure_code',
            'reviewed_at',
            'reviewed_by',
            'review_outcome',
        ] as $column) {
            if (! Schema::hasColumn('sos_alert_deliveries', $column)) {
                return false;
            }
        }

        return true;
    }
}
