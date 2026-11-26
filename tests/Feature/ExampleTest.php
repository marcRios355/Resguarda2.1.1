<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests opening the app see the welcome screen with login and register', function () {
    $this->withoutVite();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('canRegister', true));
});

test('authenticated users opening the app go straight to the risk map', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('map'));
});
