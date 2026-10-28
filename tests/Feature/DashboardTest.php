<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('map'))->assertRedirect(route('login'));
});

test('the old dashboard address opens the risk map', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect('/map');
});

test('authenticated users can visit the risk map as their main screen', function () {
    $this->withoutVite();

    $this->actingAs(User::factory()->create())
        ->get(route('map'))
        ->assertOk();
});
