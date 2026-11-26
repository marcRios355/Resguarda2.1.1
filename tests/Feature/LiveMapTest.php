<?php

use App\Models\District;
use App\Models\Report;
use App\Models\SafeZone;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('open spaces come from OpenStreetMap and are cached', function () {
    Http::fake([
        'overpass-api.de/*' => Http::response(['elements' => [
            ['type' => 'node', 'id' => 1, 'lat' => -8.11, 'lon' => -79.03, 'tags' => ['emergency' => 'assembly_point', 'name' => 'Punto INDECI']],
            ['type' => 'way', 'id' => 2, 'center' => ['lat' => -8.12, 'lon' => -79.02], 'tags' => ['leisure' => 'park', 'name' => 'Parque El Recreo']],
            ['type' => 'way', 'id' => 3, 'tags' => ['place' => 'square']],
        ]]),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('map.open-spaces'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.type', 'assembly_point')
        ->assertJsonPath('data.0.type_label', 'Punto de reunión')
        ->assertJsonPath('data.1.name', 'Parque El Recreo')
        ->assertJsonPath('data.1.latitude', -8.12);

    $this->actingAs($user)->getJson(route('map.open-spaces'))->assertJsonCount(2, 'data');

    Http::assertSentCount(1);
});

test('open spaces return an empty list when OpenStreetMap is unavailable', function () {
    Http::fake(['overpass-api.de/*' => Http::response('error', 504)]);

    $this->actingAs(User::factory()->create())->getJson(route('map.open-spaces'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('traffic tiles are proxied without exposing the provider key', function () {
    config(['services.tomtom.key' => 'clave-secreta']);
    Http::fake(['api.tomtom.com/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png'])]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('map.traffic', ['zoom' => 14, 'x' => 4740, 'y' => 7963]));

    $response->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($response->getContent())->toBe('PNGDATA')->not->toContain('clave-secreta');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/traffic/map/4/tile/flow/relative0/14/4740/7963.png')
        && str_contains($request->url(), 'key=clave-secreta'));

    $this->actingAs($user)->get(route('map.traffic', ['zoom' => 14, 'x' => 99999, 'y' => 1]))->assertNotFound();
});

test('traffic tiles are unavailable without a provider key', function () {
    config(['services.tomtom.key' => null]);
    Http::fake();

    $this->actingAs(User::factory()->create())
        ->get(route('map.traffic', ['zoom' => 14, 'x' => 4740, 'y' => 7963]))
        ->assertNotFound();

    Http::assertNothingSent();
});

test('the map estimates crowds from occupancy and recent activity only', function () {
    $district = District::factory()->create();
    $user = User::factory()->create();
    SafeZone::factory()->create(['district_id' => $district->id, 'latitude' => -8.10, 'longitude' => -79.00, 'capacity' => 100, 'current_occupancy' => 95]);
    SafeZone::factory()->create(['district_id' => $district->id, 'latitude' => -8.20, 'longitude' => -79.10, 'capacity' => 100, 'current_occupancy' => 10]);
    $report = fn (array $attributes) => Report::create([
        'user_id' => $user->id, 'district_id' => $district->id, 'incident_type' => 'calle_bloqueada', 'title' => 'Calle',
        'description' => 'Detalle', 'severity' => 'alta', 'status' => 'pending', ...$attributes,
    ]);
    $report(['latitude' => -8.05, 'longitude' => -79.05]);
    $old = $report(['latitude' => -8.15, 'longitude' => -79.15]);
    $old->forceFill(['created_at' => now()->subDay()])->save();

    $this->actingAs($user)->get(route('map'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Map')
            ->has('crowds', 2)
            ->where('crowds.0.level', 'alta')
            ->where('crowds.1.level', 'media')
            ->where('crowds.1.sources', ['Reporte de vecinos'])
            ->has('trafficEnabled')
            ->has('updatedAt'));
});

test('guests cannot use the live map endpoints', function () {
    $this->getJson(route('map.open-spaces'))->assertUnauthorized();
    $this->get(route('map.traffic', ['zoom' => 14, 'x' => 1, 'y' => 1]))->assertRedirect(route('login'));
});

test('crowded areas return the real streets around them and cache their geometry', function () {
    $district = District::factory()->create();
    SafeZone::factory()->create(['district_id' => $district->id, 'latitude' => -8.0860, 'longitude' => -79.0048, 'capacity' => 100, 'current_occupancy' => 95]);
    Http::fake([
        'overpass-api.de/*' => Http::response(['elements' => [
            ['type' => 'way', 'id' => 1, 'tags' => ['highway' => 'residential'], 'geometry' => [['lat' => -8.0861, 'lon' => -79.0049], ['lat' => -8.0870, 'lon' => -79.0050]]],
            ['type' => 'way', 'id' => 2, 'tags' => ['highway' => 'footway'], 'geometry' => [['lat' => -8.0990, 'lon' => -79.0200], ['lat' => -8.0995, 'lon' => -79.0210]]],
        ]]),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('map.crowd-streets'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.level', 'alta')
        ->assertJsonCount(1, 'data.0.streets')
        ->assertJsonPath('data.0.streets.0.0', [-8.0861, -79.0049]);

    $this->actingAs($user)->getJson(route('map.crowd-streets'))->assertJsonCount(1, 'data.0.streets');

    Http::assertSentCount(1);
});
