<?php

use App\Jobs\SendSosAlertEmail;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
});

test('admins can review uncertain SOS deliveries without exposing coordinates', function () {
    $owner = User::factory()->create();
    $alert = $owner->sosAlerts()->create([
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'delivery_status' => 'uncertain',
    ]);
    $delivery = $alert->deliveries()->create([
        'recipient_hash' => hash_hmac('sha256', 'trusted@example.test', (string) config('app.key')),
        'recipient_email' => 'trusted@example.test',
        'status' => 'uncertain',
        'failure_code' => 'outcome_unknown',
    ]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.sos-deliveries.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/SosDeliveryReviews')
            ->where('deliveries.0.id', $delivery->id)
            ->where('deliveries.0.alert_id', $alert->id)
            ->where('deliveries.0.recipient_email', 'trusted@example.test')
            ->where('deliveries.0.failure_code', 'outcome_unknown')
            ->missing('deliveries.0.latitude')
            ->missing('deliveries.0.longitude'));
});

test('non-admin users cannot inspect or resolve SOS deliveries', function () {
    $owner = User::factory()->create();
    $alert = $owner->sosAlerts()->create(['delivery_status' => 'uncertain']);
    $delivery = $alert->deliveries()->create([
        'recipient_hash' => hash_hmac('sha256', 'trusted@example.test', (string) config('app.key')),
        'recipient_email' => 'trusted@example.test',
        'status' => 'uncertain',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.sos-deliveries.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->patch(route('admin.sos-deliveries.resolve', $delivery), [
            'outcome' => 'accepted',
            'provider_message_id' => 'resend-message-id',
        ])
        ->assertForbidden();

    expect($delivery->fresh()->status)->toBe('uncertain');
});

test('admins can record a provider-confirmed acceptance without dispatching another email', function () {
    Queue::fake([SendSosAlertEmail::class]);

    $owner = User::factory()->create();
    $alert = $owner->sosAlerts()->create(['delivery_status' => 'uncertain']);
    $delivery = $alert->deliveries()->create([
        'recipient_hash' => hash_hmac('sha256', 'trusted@example.test', (string) config('app.key')),
        'recipient_email' => 'trusted@example.test',
        'status' => 'uncertain',
        'failure_code' => 'outcome_unknown',
    ]);

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patch(route('admin.sos-deliveries.resolve', $delivery), [
            'outcome' => 'accepted',
            'provider_message_id' => 'resend-message-id',
        ])
        ->assertRedirect(route('admin.sos-deliveries.index'))
        ->assertSessionHas('status');

    expect($delivery->fresh()->status)->toBe('accepted')
        ->and($delivery->fresh()->provider_message_id)->toBe('resend-message-id')
        ->and($delivery->fresh()->reviewed_by)->toBe($admin->id)
        ->and($delivery->fresh()->review_outcome)->toBe('accepted')
        ->and($alert->fresh()->delivery_status)->toBe('accepted');

    Queue::assertNotPushed(SendSosAlertEmail::class);
});

test('admins can mark a delivery as confirmed not accepted without requeueing it', function () {
    Queue::fake([SendSosAlertEmail::class]);

    $owner = User::factory()->create();
    $alert = $owner->sosAlerts()->create(['delivery_status' => 'uncertain']);
    $delivery = $alert->deliveries()->create([
        'recipient_hash' => hash_hmac('sha256', 'trusted@example.test', (string) config('app.key')),
        'recipient_email' => 'trusted@example.test',
        'status' => 'uncertain',
        'failure_code' => 'outcome_unknown',
    ]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.sos-deliveries.resolve', $delivery), [
            'outcome' => 'not_accepted',
        ])
        ->assertRedirect(route('admin.sos-deliveries.index'))
        ->assertSessionHas('status');

    expect($delivery->fresh()->status)->toBe('failed')
        ->and($delivery->fresh()->failure_code)->toBe('manual_confirmed_not_accepted')
        ->and($delivery->fresh()->review_outcome)->toBe('not_accepted')
        ->and($alert->fresh()->delivery_status)->toBe('failed');

    Queue::assertNotPushed(SendSosAlertEmail::class);
});

test('a delivery can only be resolved once and accepted outcomes require a provider message id', function () {
    $owner = User::factory()->create();
    $alert = $owner->sosAlerts()->create(['delivery_status' => 'uncertain']);
    $delivery = $alert->deliveries()->create([
        'recipient_hash' => hash_hmac('sha256', 'trusted@example.test', (string) config('app.key')),
        'recipient_email' => 'trusted@example.test',
        'status' => 'uncertain',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->from(route('admin.sos-deliveries.index'))
        ->patch(route('admin.sos-deliveries.resolve', $delivery), [
            'outcome' => 'accepted',
        ])
        ->assertSessionHasErrors(['provider_message_id']);

    expect($delivery->fresh()->status)->toBe('uncertain');

    $this->actingAs($admin)
        ->patch(route('admin.sos-deliveries.resolve', $delivery), [
            'outcome' => 'not_accepted',
        ])
        ->assertRedirect(route('admin.sos-deliveries.index'));

    $this->actingAs($admin)
        ->patch(route('admin.sos-deliveries.resolve', $delivery), [
            'outcome' => 'accepted',
            'provider_message_id' => 'resend-message-id',
        ])
        ->assertStatus(409);

    expect($delivery->fresh()->review_outcome)->toBe('not_accepted');
});
