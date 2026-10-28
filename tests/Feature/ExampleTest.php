<?php

use App\Models\User;

test('guests opening the app go straight to the login screen', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('authenticated users opening the app go straight to the risk map', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('map'));
});
