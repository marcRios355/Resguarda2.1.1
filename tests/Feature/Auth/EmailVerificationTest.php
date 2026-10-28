<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

test('email verification is disabled in Fortify', function () {
    expect(Features::enabled(Features::emailVerification()))->toBeFalse();
    expect(Route::has('verification.notice'))->toBeFalse();
    expect(Route::has('verification.verify'))->toBeFalse();
    expect(Route::has('verification.send'))->toBeFalse();
});

test('users without a verified email can access authenticated application pages', function () {
    $this->withoutVite();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('map'))
        ->assertOk();

    $this->get(route('need-help.index'))->assertOk();
    $this->get(route('profile.edit'))->assertOk();
    expect($user->fresh()->email_verified_at)->toBeNull();
});
