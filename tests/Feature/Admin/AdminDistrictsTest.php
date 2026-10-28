<?php

use App\Models\District;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin users can view the districts management list', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    District::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('admin.districts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Districts')
            ->where('districts.0.name', fn ($name) => ! empty($name))
        );
});

test('non-admin users cannot view the districts management list', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $this->actingAs($user)
        ->get(route('admin.districts'))
        ->assertForbidden();
});
