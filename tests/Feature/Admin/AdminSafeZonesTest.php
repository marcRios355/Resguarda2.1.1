<?php

use App\Models\SafeZone;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin users can view the safe zones management list', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    SafeZone::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('admin.safe-zones'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/SafeZones')
            ->where('safeZones.0.name', fn ($name) => ! empty($name))
        );
});

test('non-admin users cannot view the safe zones management list', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $this->actingAs($user)
        ->get(route('admin.safe-zones'))
        ->assertForbidden();
});
