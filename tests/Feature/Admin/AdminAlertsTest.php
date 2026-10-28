<?php

use App\Models\Alert;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin users can view the alerts management list', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    Alert::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('admin.alerts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Alerts')
            ->where('alerts.0.title', fn ($title) => ! empty($title))
        );
});

test('non-admin users cannot view the alerts management list', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $this->actingAs($user)
        ->get(route('admin.alerts'))
        ->assertForbidden();
});
