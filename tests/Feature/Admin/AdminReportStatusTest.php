<?php

use App\Models\Report;
use App\Models\User;

test('admin users can update a report status from the admin panel', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $report = Report::factory()->create([
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.reports.status.update', $report), [
            'status' => 'resolved',
        ])
        ->assertRedirect(route('admin.reports'));

    $this->assertDatabaseHas('reports', [
        'id' => $report->id,
        'status' => 'resolved',
    ]);
});

test('non-admin users cannot update a report status', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $report = Report::factory()->create();

    $this->actingAs($user)
        ->patch(route('admin.reports.status.update', $report), [
            'status' => 'resolved',
        ])
        ->assertForbidden();
});
