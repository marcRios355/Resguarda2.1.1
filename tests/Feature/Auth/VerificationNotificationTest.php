<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

test('registration does not send an email verification notification', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    event(new Registered($user));

    Notification::assertNotSentTo($user, VerifyEmail::class);
    expect(Features::enabled(Features::resetPasswords()))->toBeTrue();
});
