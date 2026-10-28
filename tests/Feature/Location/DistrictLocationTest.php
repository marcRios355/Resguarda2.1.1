<?php

use App\Models\Alert;
use App\Models\District;
use App\Models\Report;
use App\Models\SafeZone;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function locationDistrict(string $name, float $latitude, float $longitude, ?array $bounds = null): District
{
    return District::factory()->create([
        'name' => $name,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'geojson_bounds' => $bounds,
    ]);
}

test('guests cannot look up a location', function () {
    $this->getJson(route('location.lookup', ['latitude' => -8.11, 'longitude' => -79.02]))
        ->assertUnauthorized();
});

test('a point inside district boundaries is assigned to that district', function () {
    locationDistrict('Trujillo', -8.1116, -79.0287);
    $huanchaco = locationDistrict('Huanchaco', -8.0825, -79.1172, [
        'type' => 'Polygon',
        'coordinates' => [[[-79.14, -8.06], [-79.09, -8.06], [-79.09, -8.10], [-79.14, -8.10], [-79.14, -8.06]]],
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('location.lookup', ['latitude' => -8.08, 'longitude' => -79.12]))
        ->assertOk()
        ->assertJsonPath('district.id', $huanchaco->id)
        ->assertJsonPath('method', 'boundary')
        ->assertJsonPath('approximate', false);
});

test('without boundaries the nearest district is returned as approximate with nearby safe zones', function () {
    $trujillo = locationDistrict('Trujillo', -8.1116, -79.0287);
    locationDistrict('Huanchaco', -8.0825, -79.1172);
    SafeZone::factory()->create(['district_id' => $trujillo->id, 'name' => 'Lejana', 'latitude' => -8.2000, 'longitude' => -79.0000]);
    SafeZone::factory()->create(['district_id' => $trujillo->id, 'name' => 'Cercana', 'latitude' => -8.1120, 'longitude' => -79.0290]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('location.lookup', ['latitude' => -8.1118, 'longitude' => -79.0289]))
        ->assertOk()
        ->assertJsonPath('district.name', 'Trujillo')
        ->assertJsonPath('method', 'nearest_centroid')
        ->assertJsonPath('approximate', true)
        ->assertJsonPath('safe_zones.0.name', 'Cercana')
        ->assertJsonPath('safe_zones.1.name', 'Lejana');
});

test('points far from Trujillo are reported as outside the province', function () {
    locationDistrict('Trujillo', -8.1116, -79.0287);

    $this->actingAs(User::factory()->create())
        ->getJson(route('location.lookup', ['latitude' => -12.0464, 'longitude' => -77.0428]))
        ->assertOk()
        ->assertJsonPath('district', null)
        ->assertJsonPath('method', 'outside');
});

test('lookup validates coordinates', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('location.lookup', ['latitude' => 120, 'longitude' => -79]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('latitude');
});

test('reports are rejected when district boundaries prove the location belongs elsewhere', function () {
    $trujillo = locationDistrict('Trujillo', -8.1116, -79.0287);
    locationDistrict('Huanchaco', -8.0825, -79.1172, [
        ['lat' => -8.06, 'lng' => -79.14],
        ['lat' => -8.06, 'lng' => -79.09],
        ['lat' => -8.10, 'lng' => -79.09],
        ['lat' => -8.10, 'lng' => -79.14],
    ]);
    $payload = [
        'district_id' => $trujillo->id,
        'incident_type' => 'inundacion',
        'title' => 'Calle inundada',
        'description' => 'Agua acumulada.',
        'severity' => 'media',
    ];

    $this->actingAs(User::factory()->create())
        ->post(route('reports.store'), [...$payload, 'latitude' => -8.08, 'longitude' => -79.12])
        ->assertSessionHasErrors('district_id');

    $this->actingAs(User::factory()->create())
        ->post(route('reports.store'), [...$payload, 'latitude' => -8.1116, 'longitude' => -79.0287])
        ->assertSessionHasNoErrors();

    expect(Report::query()->count())->toBe(1);
});

test('the map shows registered safe zones and only active, unexpired alerts', function () {
    $this->withoutVite();
    $district = locationDistrict('Trujillo', -8.1116, -79.0287);
    SafeZone::factory()->create(['district_id' => $district->id, 'name' => 'Coliseo']);
    Alert::factory()->create(['district_id' => $district->id, 'title' => 'Vigente', 'is_active' => true, 'expires_at' => now()->addHour()]);
    Alert::factory()->create(['district_id' => $district->id, 'title' => 'Vencida', 'is_active' => true, 'expires_at' => now()->subHour()]);

    $this->actingAs(User::factory()->create())
        ->get(route('map'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('safeZones', 1)
            ->where('safeZones.0.name', 'Coliseo')
            ->has('activeAlerts', 1)
            ->where('activeAlerts.0.title', 'Vigente'));

    $this->actingAs(User::factory()->create())
        ->get(route('alerts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('alerts', 1)
            ->where('alerts.0.district_id', $district->id)
            ->has('districts', 1));
});

test('official boundaries can be imported from a GeoJSON file', function () {
    $district = locationDistrict('Víctor Larco Herrera', -8.1362, -79.0494);
    $file = tempnam(sys_get_temp_dir(), 'limites');
    file_put_contents($file, json_encode([
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'properties' => ['NOMBDIST' => 'VICTOR LARCO HERRERA'],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[-79.07, -8.12], [-79.03, -8.12], [-79.03, -8.15], [-79.07, -8.15], [-79.07, -8.12]]]],
        ]],
    ]));

    $this->artisan('districts:import-boundaries', ['file' => $file])
        ->expectsOutputToContain('Distritos actualizados: 1')
        ->assertSuccessful();

    expect($district->fresh()->geojson_bounds['type'])->toBe('Polygon');
    unlink($file);
});
