<?php

use App\Models\CitizenEvidence;
use App\Models\District;
use App\Models\DistrictZone;
use App\Models\NeedHelpRequest;
use App\Models\StoredImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

test('authenticated users can create a citizen evidence entry', function () {
    $district = District::factory()->create([
        'slug' => 'trujillo',
        'name' => 'Trujillo',
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'risk_level' => 'safe',
    ]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('citizen-evidence.store'), [
        'district_id' => $district->id,
        'incident_type' => 'inundacion',
        'title' => 'Calle bloqueada con agua',
        'description' => 'La vía principal se encuentra bloqueada por el agua.',
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'address_reference' => 'Av. España',
        'severity' => 'alta',
        'is_still_happening' => true,
    ]);

    $response->assertRedirect(route('citizen-evidence.index', ['district_id' => $district->id]));
    $this->assertDatabaseHas('citizen_evidences', [
        'user_id' => $user->id,
        'district_id' => $district->id,
        'title' => 'Calle bloqueada con agua',
        'status' => 'pending',
    ]);
});

test('evidence page provides available districts to the form', function () {
    $district = District::factory()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('citizen-evidence.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('evidence/Index')
        ->has('districts', 1)
        ->where('districts.0.name', $district->name)
        ->where('selected_district_id', $district->id));
});

test('evidence page still loads when the votes table is not migrated', function () {
    $district = District::factory()->create();
    $user = User::factory()->create();
    $evidence = CitizenEvidence::create([
        'user_id' => $user->id,
        'district_id' => $district->id,
        'incident_type' => 'inundacion',
        'title' => 'Agua acumulada en la calle',
        'description' => 'La vía se encuentra inundada.',
        'severity' => 'media',
        'status' => 'pending',
    ]);

    Schema::drop('evidence_votes');

    $response = $this->actingAs($user)->get(route('citizen-evidence.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('evidence/Index')
        ->has('evidences', 1)
        ->where('evidences.0.id', $evidence->id)
        ->where('evidences.0.vote', null));
});

test('invalid incident types are rejected before evidence is stored', function () {
    $district = District::factory()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('citizen-evidence.store'), [
        'district_id' => $district->id,
        'incident_type' => 'not_a_valid_incident',
        'title' => 'Calle bloqueada con agua',
        'description' => 'La vía principal se encuentra bloqueada por el agua.',
        'severity' => 'alta',
    ]);

    $response->assertSessionHasErrors('incident_type');
    $this->assertDatabaseCount('citizen_evidences', 0);
});

test('coordinates outside geographic ranges are rejected before evidence is stored', function () {
    $district = District::factory()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('citizen-evidence.store'), [
        'district_id' => $district->id,
        'incident_type' => 'inundacion',
        'title' => 'Calle bloqueada con agua',
        'description' => 'La vía principal se encuentra bloqueada por el agua.',
        'latitude' => 91,
        'longitude' => 181,
        'severity' => 'alta',
    ]);

    $response->assertSessionHasErrors(['latitude', 'longitude']);
    $this->assertDatabaseCount('citizen_evidences', 0);
});

test('admins can update the status of a citizen evidence entry', function () {
    $district = District::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $evidence = CitizenEvidence::create([
        'user_id' => User::factory()->create()->id,
        'district_id' => $district->id,
        'incident_type' => 'inundacion',
        'title' => 'Agua acumulada en la calle',
        'description' => 'La vía se encuentra inundada.',
        'severity' => 'media',
        'status' => 'pending',
        'is_still_happening' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('citizen-evidence.status', $evidence), [
            'status' => 'resolved',
            'is_still_happening' => false,
        ])
        ->assertSessionHas('status', 'Estado actualizado.');

    $this->assertDatabaseHas('citizen_evidences', [
        'id' => $evidence->id,
        'status' => 'resolved',
        'is_still_happening' => false,
    ]);
});

test('non-admin users cannot update another users citizen evidence entry', function () {
    $district = District::factory()->create();
    $user = User::factory()->create(['role' => 'user']);
    $evidence = CitizenEvidence::create([
        'user_id' => User::factory()->create()->id,
        'district_id' => $district->id,
        'incident_type' => 'inundacion',
        'title' => 'Agua acumulada en la calle',
        'description' => 'La vía se encuentra inundada.',
        'severity' => 'media',
        'status' => 'pending',
        'is_still_happening' => true,
    ]);

    $this->actingAs($user)
        ->patch(route('citizen-evidence.status', $evidence), [
            'status' => 'resolved',
            'is_still_happening' => false,
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('citizen_evidences', [
        'id' => $evidence->id,
        'status' => 'pending',
        'is_still_happening' => true,
    ]);
});

test('non-admin owners cannot update the status of their own citizen evidence entry', function () {
    $district = District::factory()->create();
    $owner = User::factory()->create(['role' => 'user']);
    $evidence = CitizenEvidence::create([
        'user_id' => $owner->id,
        'district_id' => $district->id,
        'incident_type' => 'inundacion',
        'title' => 'Agua acumulada en la calle',
        'description' => 'La vía se encuentra inundada.',
        'severity' => 'media',
        'status' => 'pending',
        'is_still_happening' => true,
    ]);

    $this->actingAs($owner)
        ->patch(route('citizen-evidence.status', $evidence), [
            'status' => 'resolved',
            'is_still_happening' => false,
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('citizen_evidences', [
        'id' => $evidence->id,
        'status' => 'pending',
        'is_still_happening' => true,
    ]);
});

test('authenticated users can request help in a district', function () {
    $district = District::factory()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('need-help.store'), [
        'district_id' => $district->id,
        'help_type' => 'medical',
        'description' => 'Necesitamos asistencia médica urgente para dos personas.',
        'urgency_level' => 'high',
        'address_reference' => 'Jr. Pizarro',
    ]);

    $response->assertRedirect(route('need-help.index'));
    $this->assertDatabaseHas('need_help_requests', [
        'user_id' => $user->id,
        'district_id' => $district->id,
        'help_type' => 'medical',
        'status' => 'pending',
    ]);
});

test('admins can update the status of a help request', function () {
    $district = District::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $helpRequest = NeedHelpRequest::create([
        'user_id' => User::factory()->create()->id,
        'district_id' => $district->id,
        'help_type' => 'medical',
        'description' => 'Se necesita asistencia médica.',
        'urgency_level' => 'high',
        'status' => 'pending',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('need-help.resolve', $helpRequest), [
            'status' => 'resolved',
        ])
        ->assertSessionHas('status', 'Estado actualizado.');

    $this->assertDatabaseHas('need_help_requests', [
        'id' => $helpRequest->id,
        'status' => 'resolved',
        'is_active' => false,
    ]);
});

test('non-admin users cannot update another users help request', function () {
    $district = District::factory()->create();
    $user = User::factory()->create(['role' => 'user']);
    $helpRequest = NeedHelpRequest::create([
        'user_id' => User::factory()->create()->id,
        'district_id' => $district->id,
        'help_type' => 'medical',
        'description' => 'Se necesita asistencia médica.',
        'urgency_level' => 'high',
        'status' => 'pending',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->patch(route('need-help.resolve', $helpRequest), [
            'status' => 'resolved',
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('need_help_requests', [
        'id' => $helpRequest->id,
        'status' => 'pending',
        'is_active' => true,
    ]);
});

test('non-admin owners cannot update the status of their own help request', function () {
    $district = District::factory()->create();
    $owner = User::factory()->create(['role' => 'user']);
    $helpRequest = NeedHelpRequest::create([
        'user_id' => $owner->id,
        'district_id' => $district->id,
        'help_type' => 'medical',
        'description' => 'Se necesita asistencia médica.',
        'urgency_level' => 'high',
        'status' => 'pending',
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->patch(route('need-help.resolve', $helpRequest), [
            'status' => 'resolved',
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('need_help_requests', [
        'id' => $helpRequest->id,
        'status' => 'pending',
        'is_active' => true,
    ]);
});

test('map page exposes real district risk data', function () {
    $district = District::factory()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('map'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Map')
            ->has('districts', 1)
            ->where('districts.0.name', $district->name)
            ->where('districts.0.risk', $district->risk_level));
});

test('admins can manage district zones', function () {
    $district = District::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);

    $createResponse = $this->actingAs($admin)->post(route('district-zones.store'), [
        'district_id' => $district->id,
        'name' => 'Sector Norte',
        'zone_type' => 'critical_area',
        'latitude' => -8.1100,
        'longitude' => -79.0200,
        'radius_meters' => 850,
    ]);

    $createResponse->assertRedirect(route('district-zones.index'));
    $this->assertDatabaseHas('district_zones', [
        'district_id' => $district->id,
        'name' => 'Sector Norte',
        'zone_type' => 'critical_area',
    ]);

    $zone = DistrictZone::query()->where('name', 'Sector Norte')->firstOrFail();

    $updateResponse = $this->actingAs($admin)->patch(route('district-zones.update', $zone), [
        'name' => 'Sector Norte Reforzado',
        'zone_type' => 'evacuation_route',
        'latitude' => -8.1110,
        'longitude' => -79.0210,
        'radius_meters' => 1200,
    ]);

    $updateResponse->assertRedirect(route('district-zones.index'));
    $this->assertDatabaseHas('district_zones', [
        'id' => $zone->id,
        'name' => 'Sector Norte Reforzado',
        'zone_type' => 'evacuation_route',
    ]);

    $deleteResponse = $this->actingAs($admin)->delete(route('district-zones.destroy', $zone));

    $deleteResponse->assertRedirect(route('district-zones.index'));
    $this->assertDatabaseMissing('district_zones', [
        'id' => $zone->id,
    ]);
});

test('evidence photos are stored in the database and any signed in user can see them', function () {
    $district = District::factory()->create();
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/u2QAAAAASUVORK5CYII=', true);

    $this->actingAs($author)->post(route('citizen-evidence.store'), [
        'district_id' => $district->id,
        'incident_type' => 'inundacion',
        'title' => 'Calle inundada',
        'description' => 'El agua cubre la vereda.',
        'severity' => 'media',
        'photo' => UploadedFile::fake()->createWithContent('calle.png', $png),
    ])->assertSessionHasNoErrors();

    $evidence = CitizenEvidence::sole();
    expect(StoredImage::where('path', $evidence->photo_path)->exists())->toBeTrue();

    $this->withoutVite();
    $this->actingAs($viewer)
        ->get(route('citizen-evidence.index', ['district_id' => $district->id]))
        ->assertInertia(fn ($page) => $page->where('evidences.0.photo_url', route('citizen-evidence.photo', $evidence, false)));

    $response = $this->actingAs($viewer)->get(route('citizen-evidence.photo', $evidence));
    $response->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($response->getContent())->toBe($png);

    auth()->logout();
    $this->get(route('citizen-evidence.photo', $evidence))->assertRedirect(route('login'));
});
