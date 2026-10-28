<?php

use App\Models\EmergencyContact;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can visit the resguarda mode page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('resguarda-mode'))
        ->assertOk();
});

test('authenticated users can visit the emergency page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('emergency'))
        ->assertOk();
});

test('the emergency page shows the user emergency contacts', function () {
    $user = User::factory()->create();

    EmergencyContact::create([
        'user_id' => $user->id,
        'name' => 'María López',
        'phone' => '+51987654321',
        'relationship' => 'Hermana',
    ]);

    $this->actingAs($user)
        ->get(route('emergency'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('contacts.0.name', 'María López')
            ->where('contacts.0.phone', '+51987654321')
            ->where('contacts.0.relationship', 'Hermana')
        );
});
