<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $this->withoutVite();
    Notification::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Test',
        'last_name' => 'User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('map', absolute: false));
    $user = User::where('email', 'test@example.com')->firstOrFail();
    expect($user->email_verified_at)->toBeNull();
    Notification::assertNotSentTo($user, VerifyEmail::class);

    $this->get(route('map'))->assertOk();
    $this->get(route('need-help.index'))->assertOk();
});
