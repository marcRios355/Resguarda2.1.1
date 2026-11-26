<?php

use App\Models\District;
use App\Models\SafeZone;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

/**
 * @return array<string, mixed>
 */
function safeZonePayload(District $district, array $overrides = []): array
{
    return [
        'name' => 'Centro Cultural Moche',
        'address' => 'Calle Los Próceres 145',
        'district_id' => $district->id,
        'latitude' => -8.1716,
        'longitude' => -79.0094,
        'capacity' => 140,
        'current_occupancy' => 48,
        'status' => 'available',
        'services' => ['agua', 'luz', 'banos'],
        'notes' => 'Ingreso por la puerta lateral.',
        ...$overrides,
    ];
}

test('users can publish a safe zone from their location and it starts unverified', function () {
    $user = User::factory()->create();
    $district = District::factory()->create(['geojson_bounds' => null]);

    $response = $this->actingAs($user)->post(route('safe-zones.store'), safeZonePayload($district));

    $zone = SafeZone::firstWhere('name', 'Centro Cultural Moche');

    $response->assertRedirect(route('safe-zones.show', $zone));
    expect($zone->user_id)->toBe($user->id)
        ->and($zone->is_verified)->toBeFalse()
        ->and($zone->capacity)->toBe(140)
        ->and($zone->current_occupancy)->toBe(48)
        ->and($zone->services)->toBe(['agua', 'luz', 'banos']);

    $this->actingAs($user)
        ->get(route('safe-zones.show', $zone))
        ->assertInertia(fn (Assert $page) => $page
            ->component('safe-zones/Index')
            ->where('zones.0.published_by', $user->name)
            ->where('zones.0.notes', 'Ingreso por la puerta lateral.')
            ->has('districts'));
});

test('safe zone publications are validated', function () {
    $user = User::factory()->create();
    $district = District::factory()->create(['geojson_bounds' => null]);

    $this->actingAs($user)
        ->post(route('safe-zones.store'), safeZonePayload($district, [
            'current_occupancy' => 200,
            'status' => 'inventado',
            'services' => ['piscina'],
        ]))
        ->assertSessionHasErrors(['current_occupancy', 'status', 'services.0']);

    expect(SafeZone::count())->toBe(0);
});

test('guests cannot publish safe zones', function () {
    $district = District::factory()->create();

    $this->post(route('safe-zones.store'), safeZonePayload($district))
        ->assertRedirect(route('login'));
});

test('users can mark a safe zone as reliable or not and everyone sees the totals', function () {
    $zone = SafeZone::factory()->create();
    $voter = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($voter)
        ->post(route('safe-zones.vote', $zone), ['vote' => 'reliable'])
        ->assertRedirect();

    $this->actingAs($voter)
        ->post(route('safe-zones.vote', $zone), ['vote' => 'unreliable'])
        ->assertRedirect();

    $this->actingAs($other)
        ->post(route('safe-zones.vote', $zone), ['vote' => 'reliable']);

    $this->actingAs($other)
        ->post(route('safe-zones.vote', $zone), ['vote' => 'tal vez'])
        ->assertSessionHasErrors('vote');

    $this->actingAs($voter)
        ->get(route('safe-zones.show', $zone))
        ->assertInertia(fn (Assert $page) => $page
            ->where('zones.0.reliable_votes', 1)
            ->where('zones.0.unreliable_votes', 1)
            ->where('zones.0.my_vote', 'unreliable'));
});
