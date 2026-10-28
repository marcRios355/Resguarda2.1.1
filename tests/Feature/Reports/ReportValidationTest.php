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

    $response->assertRedirect(route('reports.index'));
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
