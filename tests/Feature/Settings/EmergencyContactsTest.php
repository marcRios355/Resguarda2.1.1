<?php

use App\Models\EmergencyContact;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can manage their emergency contacts', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('emergency-contacts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/EmergencyContacts')
        );

    $this->actingAs($user)
        ->post(route('emergency-contacts.store'), [
            'name' => 'María López',
            'phone' => '+51987654321',
            'email' => 'maria@example.test',
            'relationship' => 'Hermana',
        ])
        ->assertRedirect(route('emergency-contacts.index'));

    $contact = EmergencyContact::query()->sole();

    expect($contact->user_id)->toBe($user->id)
        ->and($contact->name)->toBe('María López')
        ->and($contact->phone)->toBe('+51987654321')
        ->and($contact->email)->toBe('maria@example.test')
        ->and($contact->relationship)->toBe('Hermana');
    $this->assertDatabaseMissing('emergency_contacts', ['email' => 'maria@example.test']);

    $this->actingAs($user)
        ->put(route('emergency-contacts.update', $contact), [
            'name' => 'María López',
            'phone' => '+51987654321',
            'email' => 'maria.updated@example.test',
            'relationship' => 'Hermana',
        ])
        ->assertRedirect(route('emergency-contacts.index'));

    expect($contact->fresh()->email)->toBe('maria.updated@example.test');
});

test('contact email is optional for existing and new contacts', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('emergency-contacts.store'), [
            'name' => 'Contacto sin correo',
            'phone' => '+51987654321',
        ])
        ->assertRedirect(route('emergency-contacts.index'));

    expect(EmergencyContact::query()->sole()->email)->toBeNull();
});

test('contact email must be valid', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('emergency-contacts.index'))
        ->post(route('emergency-contacts.store'), [
            'name' => 'Contacto',
            'phone' => '+51987654321',
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrors(['email']);

    $this->assertDatabaseCount('emergency_contacts', 0);
});

test('users cannot update another users emergency contact', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $contact = EmergencyContact::create([
        'user_id' => $owner->id,
        'name' => 'Contacto privado',
        'phone' => '+51987654321',
        'email' => 'trusted@example.test',
    ]);

    $this->actingAs($otherUser)
        ->put(route('emergency-contacts.update', $contact), [
            'name' => 'Cambio no autorizado',
            'phone' => '+51987654321',
            'email' => 'attacker@example.test',
        ])
        ->assertForbidden();

    expect($contact->fresh()->name)->toBe('Contacto privado')
        ->and($contact->fresh()->email)->toBe('trusted@example.test');
});

test('users cannot access other users emergency contacts list', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    EmergencyContact::create([
        'user_id' => $owner->id,
        'name' => 'Pedro',
        'phone' => '+51999999999',
        'relationship' => 'Padre',
    ]);

    $this->actingAs($otherUser)
        ->get(route('emergency-contacts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('contacts', [])
        );
});
