<?php

use App\Models\Report;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin users can view the reports management list', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    Report::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('admin.reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Reports')
            ->where('reports.0.title', fn ($title) => ! empty($title))
        );
});

test('non-admin users cannot view the reports management list', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $this->actingAs($user)
        ->get(route('admin.reports'))
        ->assertForbidden();
});
