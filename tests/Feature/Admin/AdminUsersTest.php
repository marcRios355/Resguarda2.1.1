<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin users can view the user management list', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    User::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('admin.users'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Users')
            ->where('users.0.name', fn ($name) => ! empty($name))
        );
});

test('non-admin users cannot view the user management list', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertForbidden();
});
