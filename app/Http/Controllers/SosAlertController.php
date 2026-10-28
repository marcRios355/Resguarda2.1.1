<?php

namespace App\Http\Controllers;

use App\Jobs\SendSosAlertEmail;
use App\Models\SosAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SosAlertController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->sosSchemaReady(), 503, 'El registro SOS no está disponible. Contacta al equipo de soporte.');

        $validated = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'user_id' => ['prohibited'],
            'owner_id' => ['prohibited'],
            'recipient_ids' => ['prohibited'],
            'emergency_contact_ids' => ['prohibited'],
        ]);

        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $emailSchemaReady = $this->emailSchemaReady();

        $alert = DB::transaction(function () use ($user, $validated, $emailSchemaReady): SosAlert {
            $alert = $user->sosAlerts()->firstOrCreate(
                ['idempotency_key' => $validated['idempotency_key']],
                [
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'delivery_status' => 'registered',
                ],
            );

            if ($alert->wasRecentlyCreated) {
                if (! $emailSchemaReady) {
                    $alert->forceFill(['delivery_status' => 'unavailable'])->save();

                    return $alert->fresh() ?? $alert;
                }

                $contacts = $user->emergencyContacts()->get();
                $alert->emergencyContacts()->sync($contacts->modelKeys());

                $contactsWithEmail = $contacts
                    ->filter(fn ($contact): bool => is_string($contact->email) && trim($contact->email) !== '')
                    ->groupBy(fn ($contact): string => Str::lower(trim($contact->email)));

                foreach ($contactsWithEmail->keys() as $email) {
                    $alert->deliveries()->create([
                        'recipient_hash' => hash_hmac('sha256', $email, (string) config('app.key')),
                        'recipient_email' => $email,
                    ]);
                }

                $alert->refreshDeliveryStatus();
            }

            return $alert->fresh() ?? $alert;
        });

        if ($emailSchemaReady) {
            foreach ($alert->deliveries()->where('status', 'pending')->pluck('id') as $deliveryId) {
                SendSosAlertEmail::dispatch((int) $deliveryId);
            }
        }

        if (! $emailSchemaReady) {
            return to_route('need-help.index')->with(
                'sosFeedback',
                'SOS registrado, pero la notificación por correo no está disponible hasta aplicar las migraciones pendientes.',
            );
        }

        $recipientCount = $alert->deliveries()->count();
        $message = match ($alert->delivery_status) {
            'pending' => "SOS registrado. La notificación está pendiente de envío a {$recipientCount} contacto(s).",
            'accepted' => "SOS registrado. El proveedor aceptó la notificación para {$recipientCount} contacto(s); esto no confirma su entrega o lectura.",
            'partially_accepted' => 'SOS registrado. La notificación tuvo un resultado parcial; revisa el estado actualizado en esta página.',
            'uncertain' => 'SOS registrado. No se pudo confirmar si el proveedor aceptó la notificación; revisa el estado antes de iniciar otra alerta.',
            'partially_uncertain' => 'SOS registrado. Algunas notificaciones tienen un resultado incierto; revisa el estado antes de iniciar otra alerta.',
            'failed' => 'SOS registrado, pero el proveedor no aceptó la notificación.',
            'unavailable' => 'SOS registrado, pero la notificación por correo no está disponible hasta aplicar las migraciones pendientes.',
            default => 'SOS registrado, pero no se pudo enviar la notificación: ningún contacto tiene un correo válido configurado.',
        };

        return to_route('need-help.index')->with('sosFeedback', $message);
    }

    private function emailSchemaReady(): bool
    {
        if (! Schema::hasTable('emergency_contacts')
            || ! Schema::hasTable('sos_alert_emergency_contact')
            || ! Schema::hasTable('sos_alert_deliveries')) {
            return false;
        }

        foreach ([
            ['emergency_contacts', 'user_id'],
            ['emergency_contacts', 'email'],
            ['sos_alert_emergency_contact', 'sos_alert_id'],
            ['sos_alert_emergency_contact', 'emergency_contact_id'],
            ['sos_alert_deliveries', 'id'],
            ['sos_alert_deliveries', 'sos_alert_id'],
            ['sos_alert_deliveries', 'recipient_hash'],
            ['sos_alert_deliveries', 'recipient_email'],
            ['sos_alert_deliveries', 'status'],
            ['sos_alert_deliveries', 'attempt_count'],
            ['sos_alert_deliveries', 'first_attempted_at'],
            ['sos_alert_deliveries', 'accepted_at'],
            ['sos_alert_deliveries', 'provider_message_id'],
            ['sos_alert_deliveries', 'failure_code'],
            ['sos_alert_deliveries', 'request_payload'],
            ['sos_alert_deliveries', 'created_at'],
            ['sos_alert_deliveries', 'updated_at'],
        ] as [$table, $column]) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function sosSchemaReady(): bool
    {
        if (! Schema::hasTable('sos_alerts')) {
            return false;
        }

        foreach (['id', 'user_id', 'latitude', 'longitude', 'delivery_status', 'idempotency_key', 'created_at', 'updated_at'] as $column) {
            if (! Schema::hasColumn('sos_alerts', $column)) {
                return false;
            }
        }

        return true;
    }
}
