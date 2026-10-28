<?php

use App\Jobs\SendSosAlertEmail;
use App\Mail\SosAlertNotification;
use App\Models\EmergencyContact;
use App\Models\SosAlert;
use App\Models\SosAlertDelivery;
use App\Models\User;
use App\Services\ResendSosEmailSender;
use App\Services\SosEmailDeliveryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
});

test('authenticated users can register a pending SOS linked to only their emergency contacts', function () {
    Queue::fake([SendSosAlertEmail::class]);

    $user = User::factory()->create();
    $contact = EmergencyContact::create([
        'user_id' => $user->id,
        'name' => 'Contacto de confianza',
        'phone' => '+51987654321',
        'email' => 'trusted@example.test',
        'relationship' => 'Familiar',
    ]);
    $otherUser = User::factory()->create();
    $otherContact = EmergencyContact::create([
        'user_id' => $otherUser->id,
        'name' => 'Contacto ajeno',
        'phone' => '+51999999999',
        'email' => 'other@example.test',
        'relationship' => 'Familiar',
    ]);

    $this->actingAs($user)
        ->post(route('need-help.sos'), [
            'idempotency_key' => (string) Str::uuid(),
            'latitude' => -8.1116,
            'longitude' => -79.0287,
        ])
        ->assertRedirect(route('need-help.index'))
        ->assertSessionHas('sosFeedback', fn (string $message): bool => str_contains($message, 'pendiente de envío'));

    $alert = SosAlert::query()->sole();
    $delivery = SosAlertDelivery::query()->sole();

    expect($alert->user_id)->toBe($user->id)
        ->and($alert->delivery_status)->toBe('pending')
        ->and($alert->latitude)->toBe(-8.1116)
        ->and($alert->longitude)->toBe(-79.0287)
        ->and($alert->created_at)->not->toBeNull()
        ->and($alert->emergencyContacts()->pluck('emergency_contacts.id')->all())->toBe([$contact->id])
        ->and($alert->emergencyContacts()->whereKey($otherContact->id)->exists())->toBeFalse()
        ->and($delivery->recipient_email)->toBe('trusted@example.test');
    $this->assertDatabaseMissing('sos_alert_deliveries', ['recipient_email' => 'trusted@example.test']);

    Queue::assertPushed(SendSosAlertEmail::class, fn (SendSosAlertEmail $job): bool => $job->deliveryId === $delivery->id);
});

test('guests cannot register SOS alerts', function () {
    $this->post(route('need-help.sos'))
        ->assertRedirect(route('login'));

    $this->assertDatabaseCount('sos_alerts', 0);
});

test('authenticated SOS requests require an idempotency key', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('need-help.index'))
        ->post(route('need-help.sos'))
        ->assertSessionHasErrors(['idempotency_key']);

    $this->assertDatabaseCount('sos_alerts', 0);
});

test('SOS coordinates are optional but validated when provided', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('need-help.sos'), [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertRedirect(route('need-help.index'));

    $alert = SosAlert::query()->sole();
    expect($alert->latitude)->toBeNull()
        ->and($alert->longitude)->toBeNull()
        ->and($alert->delivery_status)->toBe('no_recipients');

    $this->actingAs(User::factory()->create())
        ->from(route('need-help.index'))
        ->post(route('need-help.sos'), [
            'idempotency_key' => (string) Str::uuid(),
            'latitude' => 91,
            'longitude' => 181,
        ])
        ->assertSessionHasErrors(['latitude', 'longitude']);

    $this->assertDatabaseCount('sos_alerts', 1);
});

test('users cannot choose an SOS owner or arbitrary recipients', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $contact = EmergencyContact::create([
        'user_id' => $otherUser->id,
        'name' => 'Contacto ajeno',
        'phone' => '+51999999999',
        'relationship' => 'Familiar',
    ]);

    $this->actingAs($user)
        ->from(route('need-help.index'))
        ->post(route('need-help.sos'), [
            'idempotency_key' => (string) Str::uuid(),
            'user_id' => $otherUser->id,
            'recipient_ids' => [$contact->id],
        ])
        ->assertSessionHasErrors(['user_id', 'recipient_ids']);

    $this->assertDatabaseCount('sos_alerts', 0);
});

test('SOS without valid email recipients is saved and clearly reported as not sent', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('need-help.sos'), [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertRedirect(route('need-help.index'))
        ->assertSessionHas('sosFeedback', 'SOS registrado, pero no se pudo enviar la notificación: ningún contacto tiene un correo válido configurado.');

    $alert = SosAlert::query()->sole();
    expect($alert->delivery_status)->toBe('no_recipients')
        ->and($alert->emergencyContacts()->count())->toBe(0)
        ->and($alert->deliveries()->count())->toBe(0);

    Queue::assertNothingPushed();
});

test('SOS is saved but not queued when the required email payload migration is missing', function () {
    Queue::fake([SendSosAlertEmail::class]);
    $user = User::factory()->create();
    EmergencyContact::create([
        'user_id' => $user->id,
        'name' => 'Contacto de confianza',
        'phone' => '+51987654321',
        'email' => 'trusted@example.test',
    ]);

    Schema::shouldReceive('hasTable')->andReturn(true);
    Schema::shouldReceive('hasColumn')->andReturnUsing(
        fn (string $table, string $column): bool => ! ($table === 'sos_alert_deliveries' && $column === 'request_payload'),
    );

    $this->actingAs($user)
        ->post(route('need-help.sos'), [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertRedirect(route('need-help.index'))
        ->assertSessionHas('sosFeedback', 'SOS registrado, pero la notificación por correo no está disponible hasta aplicar las migraciones pendientes.');

    $alert = SosAlert::query()->sole();
    expect($alert->delivery_status)->toBe('unavailable')
        ->and($alert->deliveries()->count())->toBe(0);

    Queue::assertNothingPushed();
});

test('community help listing does not expose SOS contacts or coordinates', function () {
    $user = User::factory()->create();
    $contact = EmergencyContact::create([
        'user_id' => $user->id,
        'name' => 'Contacto privado',
        'phone' => '+51987654321',
        'email' => 'private@example.test',
        'relationship' => 'Familiar',
    ]);

    $alert = $user->sosAlerts()->create([
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'delivery_status' => 'registered',
    ]);
    $alert->emergencyContacts()->sync([$contact->id]);
    $alert->deliveries()->create([
        'recipient_hash' => hash_hmac('sha256', 'private@example.test', (string) config('app.key')),
        'recipient_email' => 'private@example.test',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('need-help.index'))
        ->assertOk()
        ->assertDontSee('+51987654321')
        ->assertInertia(fn ($page) => $page
            ->component('help/Index')
            ->missing('sosAlerts')
            ->missing('sosAlert.latitude')
            ->missing('sosAlert.longitude')
            ->missing('sosAlert.deliveries')
            ->missing('requests.0.latitude')
            ->missing('requests.0.longitude'));
});

test('SOS endpoint is rate limited per authenticated user', function () {
    $user = User::factory()->create();

    foreach (range(1, 3) as $attempt) {
        $this->actingAs($user)
            ->post(route('need-help.sos'), [
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('need-help.index'));
    }

    $this->actingAs($user)
        ->post(route('need-help.sos'), [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertTooManyRequests();

    $this->assertDatabaseCount('sos_alerts', 3);
});

test('repeated requests with one idempotency key create one alert and one delivery job', function () {
    Queue::fake([SendSosAlertEmail::class]);

    $user = User::factory()->create();
    EmergencyContact::create([
        'user_id' => $user->id,
        'name' => 'Contacto único',
        'phone' => '+51987654321',
        'email' => 'trusted@example.test',
    ]);
    $payload = ['idempotency_key' => (string) Str::uuid()];

    $this->actingAs($user)->post(route('need-help.sos'), $payload)->assertRedirect(route('need-help.index'));
    $this->actingAs($user)->post(route('need-help.sos'), $payload)->assertRedirect(route('need-help.index'));

    $this->assertDatabaseCount('sos_alerts', 1);
    $this->assertDatabaseCount('sos_alert_deliveries', 1);
    Queue::assertPushed(SendSosAlertEmail::class, 1);
});

test('duplicate contact email addresses produce only one delivery for an alert', function () {
    Queue::fake([SendSosAlertEmail::class]);

    $user = User::factory()->create();

    foreach (['trusted@example.test', 'TRUSTED@example.test'] as $email) {
        EmergencyContact::create([
            'user_id' => $user->id,
            'name' => 'Contacto',
            'phone' => '+51987654321',
            'email' => $email,
        ]);
    }

    $this->actingAs($user)->post(route('need-help.sos'), [
        'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect(route('need-help.index'));

    $this->assertDatabaseCount('sos_alert_deliveries', 1);
    Queue::assertPushed(SendSosAlertEmail::class, 1);
});

test('Resend receives only the owner contact email associated with the SOS', function () {
    Queue::fake([SendSosAlertEmail::class]);
    config([
        'services.resend.sos_provider' => 'resend',
        'services.resend.key' => 'test-only-key',
    ]);

    $user = User::factory()->create();
    EmergencyContact::create([
        'user_id' => $user->id,
        'name' => 'Contacto propio',
        'phone' => '+51987654321',
        'email' => 'trusted@example.test',
    ]);

    $otherUser = User::factory()->create();
    EmergencyContact::create([
        'user_id' => $otherUser->id,
        'name' => 'Contacto ajeno',
        'phone' => '+51999999999',
        'email' => 'other@example.test',
    ]);

    $this->actingAs($user)->post(route('need-help.sos'), [
        'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect(route('need-help.index'));

    $delivery = SosAlertDelivery::query()->sole();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.resend.com/emails' => Http::response(['id' => 'provider-message-id']),
    ]);
    (new SendSosAlertEmail($delivery->id))->handle(app(ResendSosEmailSender::class));

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.resend.com/emails'
        && $request->data()['to'] === ['trusted@example.test']
        && ! in_array('other@example.test', $request->data()['to'], true));
});

test('Resend retries use a stable idempotency key and accepted jobs do not send again', function () {
    config([
        'services.resend.sos_provider' => 'resend',
        'services.resend.key' => 'test-only-key',
    ]);

    $user = User::factory()->create();
    $alert = $user->sosAlerts()->create([
        'delivery_status' => 'pending',
    ]);
    $delivery = $alert->deliveries()->create([
        'recipient_hash' => hash_hmac('sha256', 'trusted@example.test', (string) config('app.key')),
        'recipient_email' => 'trusted@example.test',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.resend.com/emails' => Http::sequence()
            ->push(['message' => 'temporary upstream detail'], 503)
            ->push(['id' => 'provider-message-id'], 200),
    ]);

    $job = new SendSosAlertEmail($delivery->id);
    $sender = app(ResendSosEmailSender::class);

    expect(fn () => $job->handle($sender))->toThrow(SosEmailDeliveryException::class, 'No se pudo procesar la notificación SOS.');
    config([
        'mail.from.name' => 'Changed sender after first request',
        'mail.from.address' => 'changed@example.test',
    ]);
    $job->handle($sender);
    $job->handle($sender);

    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => $request->hasHeader('Idempotency-Key', 'sos-alert-delivery-'.$delivery->id));
    $requests = Http::recorded()->map(fn (array $recorded): Request => $recorded[0]);
    expect($requests->map(fn (Request $request): string => $request->body())->unique())->toHaveCount(1);
    expect($delivery->fresh()->request_payload['from'])->not->toContain('changed@example.test')
        ->and(DB::table('sos_alert_deliveries')->where('id', $delivery->id)->value('request_payload'))
        ->not->toContain('trusted@example.test');
    expect($delivery->fresh()->status)->toBe('accepted')
        ->and($alert->fresh()->delivery_status)->toBe('accepted');
});

test('Resend retries concurrent idempotent conflicts with the same request', function () {
    config([
        'services.resend.sos_provider' => 'resend',
        'services.resend.key' => 'test-only-key',
    ]);

    $user = User::factory()->create();
    $alert = $user->sosAlerts()->create([
        'delivery_status' => 'pending',
    ]);
    $delivery = $alert->deliveries()->create([
        'recipient_hash' => hash_hmac('sha256', 'trusted@example.test', (string) config('app.key')),
        'recipient_email' => 'trusted@example.test',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.resend.com/emails' => Http::sequence()
            ->push(['name' => 'concurrent_idempotent_requests'], 409)
            ->push(['id' => 'provider-message-id']),
    ]);

    $job = new SendSosAlertEmail($delivery->id);
    $sender = app(ResendSosEmailSender::class);

    expect(fn () => $job->handle($sender))
        ->toThrow(SosEmailDeliveryException::class, 'No se pudo procesar la notificación SOS.');
    $job->handle($sender);

    $requests = Http::recorded()->map(fn (array $recorded): Request => $recorded[0]);
    Http::assertSentCount(2);
    expect($requests->map(fn (Request $request): string => $request->body())->unique())->toHaveCount(1)
        ->and($delivery->fresh()->status)->toBe('accepted')
        ->and($alert->fresh()->delivery_status)->toBe('accepted');
    Http::assertSent(fn ($request): bool => $request->hasHeader('Idempotency-Key', 'sos-alert-delivery-'.$delivery->id));
});

test('invalid idempotent conflicts are not retried and remain uncertain', function () {
    config([
        'services.resend.sos_provider' => 'resend',
        'services.resend.key' => 'test-only-key',
    ]);

    $user = User::factory()->create();
    $alert = $user->sosAlerts()->create([
        'delivery_status' => 'pending',
    ]);
    $delivery = $alert->deliveries()->create([
        'recipient_hash' => hash_hmac('sha256', 'trusted@example.test', (string) config('app.key')),
        'recipient_email' => 'trusted@example.test',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.resend.com/emails' => Http::response([
            'name' => 'invalid_idempotent_request',
            'message' => 'private provider detail',
        ], 409),
    ]);
    Log::spy();

    (new SendSosAlertEmail($delivery->id))->handle(app(ResendSosEmailSender::class));

    Http::assertSentCount(1);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $context['delivery_id'] === $delivery->id
        && $context['http_status'] === 409
        && $context['failure_code'] === 'invalid_idempotent_request'
        && ! str_contains(json_encode($context), 'private provider detail'));
    expect($delivery->fresh()->status)->toBe('uncertain')
        ->and($delivery->fresh()->failure_code)->toBe('invalid_idempotent_request')
        ->and($alert->fresh()->delivery_status)->toBe('uncertain');
    $this->actingAs($user)->get(route('need-help.index'))
        ->assertInertia(fn ($page) => $page
            ->where('sosAlert.delivery_status', 'uncertain')
            ->where('sosAlert.uncertain_count', 1));
});

test('unknown HTTP 409 errors are safely recorded without automatic retries', function () {
    config([
        'services.resend.sos_provider' => 'resend',
        'services.resend.key' => 'test-only-key',
    ]);

    $alert = User::factory()->create()->sosAlerts()->create([
        'delivery_status' => 'pending',
    ]);
    $delivery = $alert->deliveries()->create([
        'recipient_hash' => hash_hmac('sha256', 'trusted@example.test', (string) config('app.key')),
        'recipient_email' => 'trusted@example.test',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.resend.com/emails' => Http::response([
            'name' => 'unrecognized_conflict_code',
            'message' => 'private provider detail',
        ], 409),
    ]);

    (new SendSosAlertEmail($delivery->id))->handle(app(ResendSosEmailSender::class));

    Http::assertSentCount(1);
    expect($delivery->fresh()->status)->toBe('uncertain')
        ->and($delivery->fresh()->failure_code)->toBe('provider_conflict_unknown')
        ->and($alert->fresh()->delivery_status)->toBe('uncertain');
});

test('Resend treats request timeouts as retryable failures', function () {
    config([
        'services.resend.sos_provider' => 'resend',
        'services.resend.key' => 'test-only-key',
    ]);

    $alert = User::factory()->create()->sosAlerts()->create([
        'delivery_status' => 'pending',
    ]);
    $delivery = $alert->deliveries()->create([
        'recipient_hash' => hash_hmac('sha256', 'trusted@example.test', (string) config('app.key')),
        'recipient_email' => 'trusted@example.test',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.resend.com/emails' => Http::response([], 408),
    ]);

    expect(fn () => (new SendSosAlertEmail($delivery->id))->handle(app(ResendSosEmailSender::class)))
        ->toThrow(SosEmailDeliveryException::class, 'No se pudo procesar la notificación SOS.');
    expect($delivery->fresh()->status)->toBe('pending');
});

test('retry exhaustion is bounded to 48 attempts and preserves an uncertain outcome', function () {
    config([
        'services.resend.sos_provider' => 'resend',
        'services.resend.key' => 'test-only-key',
    ]);

    $alert = User::factory()->create()->sosAlerts()->create([
        'delivery_status' => 'pending',
    ]);
    $delivery = $alert->deliveries()->create([
        'recipient_hash' => hash_hmac('sha256', 'trusted@example.test', (string) config('app.key')),
        'recipient_email' => 'trusted@example.test',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.resend.com/emails' => Http::response(['name' => 'service_unavailable'], 503),
    ]);

    $job = new SendSosAlertEmail($delivery->id);
    $sender = app(ResendSosEmailSender::class);
    $lastException = null;

    expect($job->tries)->toBe(48)
        ->and($job->backoff)->toBe([30, 60, 120, 300, 600, 900, 1800]);
    $maximumBackoffSeconds = array_sum($job->backoff)
        + (($job->tries - 1 - count($job->backoff)) * end($job->backoff));
    expect($maximumBackoffSeconds)->toBe(75_810)
        ->and($maximumBackoffSeconds + ($job->tries * 20))->toBeLessThan(23 * 60 * 60);

    for ($attempt = 0; $attempt < $job->tries; $attempt++) {
        try {
            $job->handle($sender);
        } catch (SosEmailDeliveryException $exception) {
            $lastException = $exception;
        }
    }

    $job->failed($lastException);

    Http::assertSentCount(48);
    expect($delivery->fresh()->attempt_count)->toBe(48)
        ->and($delivery->fresh()->status)->toBe('uncertain')
        ->and($delivery->fresh()->failure_code)->toBe('provider_unavailable')
        ->and($alert->fresh()->delivery_status)->toBe('uncertain');
});

test('expired idempotency windows do not send and retain uncertainty', function () {
    $this->travelTo(now());
    $alert = User::factory()->create()->sosAlerts()->create([
        'delivery_status' => 'pending',
    ]);
    $delivery = $alert->deliveries()->create([
        'recipient_hash' => hash_hmac('sha256', 'trusted@example.test', (string) config('app.key')),
        'recipient_email' => 'trusted@example.test',
        'first_attempted_at' => now()->subHours(23),
        'attempt_count' => 1,
        'failure_code' => 'outcome_unknown',
    ]);

    Http::preventStrayRequests();

    (new SendSosAlertEmail($delivery->id))->handle(app(ResendSosEmailSender::class));

    expect($delivery->fresh()->status)->toBe('uncertain')
        ->and($delivery->fresh()->failure_code)->toBe('idempotency_window_expired_uncertain')
        ->and($alert->fresh()->delivery_status)->toBe('uncertain');
});

test('provider rejection is stored safely while the SOS alert remains registered', function () {
    Queue::fake([SendSosAlertEmail::class]);
    config([
        'services.resend.sos_provider' => 'resend',
        'services.resend.key' => 'test-only-key',
    ]);

    $user = User::factory()->create();
    EmergencyContact::create([
        'user_id' => $user->id,
        'name' => 'Contacto autorizado',
        'phone' => '+51987654321',
        'email' => 'trusted@example.test',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.resend.com/emails' => Http::response([
            'message' => 'private provider diagnostic and recipient@example.test',
        ], 422),
    ]);

    $this->actingAs($user)
        ->post(route('need-help.sos'), [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertRedirect(route('need-help.index'))
        ->assertSessionHas('sosFeedback', fn (string $message): bool => str_contains($message, 'pendiente de envío'));

    $alert = SosAlert::query()->sole();
    $delivery = SosAlertDelivery::query()->sole();
    (new SendSosAlertEmail($delivery->id))->handle(app(ResendSosEmailSender::class));

    expect($delivery->fresh()->status)->toBe('failed')
        ->and($delivery->fresh()->failure_code)->toBe('provider_rejected')
        ->and($alert->fresh()->delivery_status)->toBe('failed');

    $this->assertDatabaseHas('sos_alerts', ['id' => $alert->id]);
    $this->actingAs($user)->get(route('need-help.index'))
        ->assertInertia(fn ($page) => $page
            ->where('sosAlert.delivery_status', 'failed')
            ->missing('sosAlert.deliveries.0.recipient_email'));
    $this->assertDatabaseMissing('sos_alert_deliveries', ['recipient_email' => 'trusted@example.test']);
    $this->assertDatabaseMissing('sos_alert_deliveries', ['failure_code' => 'private provider diagnostic and recipient@example.test']);

    Queue::assertPushed(SendSosAlertEmail::class, 1);
});

test('SOS email includes coordinates only when the user provided them', function () {
    $user = User::factory()->create();

    $withCoordinates = $user->sosAlerts()->create([
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'delivery_status' => 'pending',
    ]);
    $withoutCoordinates = $user->sosAlerts()->create([
        'delivery_status' => 'pending',
    ]);

    $withCoordinatesContent = (new SosAlertNotification($withCoordinates))->render();
    $withoutCoordinatesContent = (new SosAlertNotification($withoutCoordinates))->render();

    expect($withCoordinatesContent)->toContain('-8.1116')->toContain('-79.0287')
        ->and($withoutCoordinatesContent)->not->toContain('Ubicación compartida');
});
