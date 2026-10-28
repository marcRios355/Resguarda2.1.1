<?php

use App\Models\User;

test('admin users can access the admin panel', function () {
    $user = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->actingAs($user)
        ->get(route('admin.index'))
        ->assertOk();
});

test('non-admin users cannot access the admin panel', function () {
    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $this->actingAs($user)
        ->get(route('admin.index'))
        ->assertForbidden();
});
