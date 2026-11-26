<?php

use App\Models\District;
use App\Models\Report;
use App\Models\ReportConfirmation;
use App\Models\User;

test('authenticated users can create a report with pending status', function () {
    $district = District::firstOrCreate([
        'slug' => 'trujillo',
    ], [
        'name' => 'Trujillo',
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'risk_level' => 'safe',
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('reports.store'), [
        'district_id' => $district->id,
        'incident_type' => 'inundacion',
        'title' => 'Calle inundada',
        'description' => 'Hay agua acumulada en la intersección principal.',
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'address_reference' => 'Jr. Pizarro',
        'severity' => 'alta',
    ]);

    $response->assertRedirect(route('reports.show', Report::firstWhere('title', 'Calle inundada')));
    $this->assertDatabaseHas('reports', [
        'user_id' => $user->id,
        'title' => 'Calle inundada',
        'status' => 'pending',
    ]);
});

test('report status changes based on confirmatory votes', function () {
    $district = District::firstOrCreate([
        'slug' => 'trujillo',
    ], [
        'name' => 'Trujillo',
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'risk_level' => 'safe',
    ]);

    $user = User::factory()->create();
    $report = Report::create([
        'user_id' => $user->id,
        'district_id' => $district->id,
        'incident_type' => 'calle_bloqueada',
        'title' => 'Via cerrada',
        'description' => 'Calle bloqueada en la zona central.',
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'address_reference' => 'Av. España',
        'severity' => 'media',
        'status' => 'pending',
        'confirmations_count' => 0,
        'rejections_count' => 0,
    ]);

    foreach (range(1, 5) as $index) {
        $voter = User::factory()->create();

        ReportConfirmation::create([
            'report_id' => $report->id,
            'user_id' => $voter->id,
            'vote' => 'confirm',
        ]);
    }

    $report->refresh();
    $report->updateStatusFromVotes();

    expect($report->status)->toBe('confirmed');
});

test('users can publish a safe zone and invalid incident types are rejected', function () {
    $district = District::firstOrCreate(['slug' => 'trujillo'], [
        'name' => 'Trujillo',
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'risk_level' => 'safe',
    ]);
    $user = User::factory()->create();
    $payload = [
        'district_id' => $district->id,
        'title' => 'Plaza despejada',
        'description' => 'La plaza está libre y sirve como punto de reunión.',
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'severity' => 'baja',
    ];

    $this->actingAs($user)
        ->post(route('reports.store'), [...$payload, 'incident_type' => 'invento'])
        ->assertSessionHasErrors('incident_type');

    $this->actingAs($user)
        ->post(route('reports.store'), [...$payload, 'incident_type' => 'zona_segura'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('reports', ['title' => 'Plaza despejada', 'incident_type' => 'zona_segura']);
});

test('the map lists each publication with a link to it and the link opens the publication', function () {
    $district = District::firstOrCreate(['slug' => 'trujillo'], [
        'name' => 'Trujillo',
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'risk_level' => 'safe',
    ]);
    $user = User::factory()->create();
    $report = Report::create([
        'user_id' => $user->id,
        'district_id' => $district->id,
        'incident_type' => 'calle_bloqueada',
        'title' => 'Árbol caído',
        'description' => 'Un árbol bloquea la calle.',
        'latitude' => -8.1101,
        'longitude' => -79.0251,
        'severity' => 'alta',
        'status' => 'pending',
    ]);
    Report::create([
        'user_id' => $user->id,
        'district_id' => $district->id,
        'incident_type' => 'otro',
        'title' => 'Publicación falsa',
        'description' => 'Rechazada por la comunidad.',
        'latitude' => -8.1,
        'longitude' => -79.0,
        'severity' => 'baja',
        'status' => 'rejected',
    ]);

    $this->withoutVite();

    $this->actingAs($user)
        ->get(route('map'))
        ->assertInertia(fn ($page) => $page
            ->component('Map')
            ->has('reports', 1)
            ->where('reports.0.id', $report->id)
            ->where('reports.0.severity', 'alta')
            ->where('reports.0.url', route('reports.show', $report)));

    $this->actingAs($user)
        ->get(route('reports.show', $report))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Show')
            ->where('report.title', 'Árbol caído')
            ->where('report.vote', null));
});
