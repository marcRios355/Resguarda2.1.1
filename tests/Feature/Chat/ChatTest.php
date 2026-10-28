<?php

use App\Models\CommunityMessage;
use App\Models\Conversation;
use App\Models\District;
use App\Models\PrivateMessage;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can publish a community message', function () {
    $user = User::factory()->create();
    $district = District::firstOrCreate([
        'slug' => 'trujillo',
    ], [
        'name' => 'Trujillo',
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'risk_level' => 'safe',
    ]);

    $response = $this->actingAs($user)->post(route('chat.store'), [
        'district_id' => $district->id,
        'message' => 'Hay mucho tránsito en la zona central.',
    ]);

    $response->assertRedirect(route('communication.index', ['tab' => 'community']));
    $this->assertDatabaseHas('community_messages', [
        'user_id' => $user->id,
        'district_id' => $district->id,
        'message' => 'Hay mucho tránsito en la zona central.',
    ]);
});

test('authenticated users can start a private conversation and send a message', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    $response = $this->actingAs($sender)->post(route('private-chat.store'), [
        'recipient_id' => $recipient->id,
        'message' => 'Necesito confirmar la ruta de evacuación.',
    ]);

    $response->assertRedirect(route('communication.index', ['tab' => 'private']));
    $this->assertDatabaseHas('conversations', [
        'user_one_id' => $sender->id,
        'user_two_id' => $recipient->id,
    ]);
    $this->assertDatabaseHas('private_messages', [
        'sender_id' => $sender->id,
        'recipient_id' => $recipient->id,
        'message' => 'Necesito confirmar la ruta de evacuación.',
    ]);
});

test('private chat search returns matching users without the current user', function () {
    $user = User::factory()->create([
        'name' => 'Ana Resguarda',
        'username' => 'ana-resguarda',
    ]);
    $match = User::factory()->create([
        'name' => 'Bruno Trujillo',
        'username' => 'bruno-trujillo',
    ]);
    User::factory()->create([
        'name' => 'Carla Norte',
        'username' => 'carla-norte',
    ]);

    $this->actingAs($user)
        ->get(route('communication.index', ['tab' => 'private', 'search' => 'bruno']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('communication/Index')
            ->has('users', 1)
            ->where('users.0.id', $match->id)
            ->where('search', 'bruno')
        );
});

test('legacy chat routes open the matching communication center tab', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('chat.index', ['district_id' => 4]))
        ->assertRedirect(route('communication.index', ['tab' => 'community', 'district_id' => 4]));

    $this->actingAs($user)
        ->get(route('private-chat.index', ['search' => 'ana']))
        ->assertRedirect(route('communication.index', ['tab' => 'private', 'search' => 'ana']));
});

test('communication center filters community messages by district and excludes hidden messages', function () {
    $user = User::factory()->create();
    $district = District::firstOrCreate(['slug' => 'trujillo'], [
        'name' => 'Trujillo',
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'risk_level' => 'safe',
    ]);
    $otherDistrict = District::factory()->create();

    $visibleMessage = CommunityMessage::create([
        'user_id' => $user->id,
        'district_id' => $district->id,
        'message' => 'Mensaje visible',
        'is_hidden' => false,
    ]);
    CommunityMessage::create([
        'user_id' => $user->id,
        'district_id' => $otherDistrict->id,
        'message' => 'Otro distrito',
        'is_hidden' => false,
    ]);
    CommunityMessage::create([
        'user_id' => $user->id,
        'district_id' => $district->id,
        'message' => 'Mensaje oculto',
        'is_hidden' => true,
    ]);

    $this->actingAs($user)
        ->get(route('communication.index', ['tab' => 'community', 'district_id' => $district->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('communication/Index')
            ->has('communityMessages', 1)
            ->where('communityMessages.0.id', $visibleMessage->id)
            ->where('selectedDistrictId', $district->id)
        );
});

test('conversation participants can read and reply while other users are forbidden', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $outsider = User::factory()->create();
    $conversation = Conversation::create([
        'user_one_id' => min($sender->id, $recipient->id),
        'user_two_id' => max($sender->id, $recipient->id),
        'last_message_at' => now(),
    ]);
    PrivateMessage::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
        'recipient_id' => $recipient->id,
        'message' => 'Mensaje previo',
    ]);

    $this->actingAs($sender)
        ->get(route('communication.index', ['tab' => 'private']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('communication/Index')
            ->has('conversations', 1)
            ->where('conversations.0.last_message', 'Mensaje previo')
        );

    $this->actingAs($recipient)
        ->get(route('private-chat.show', $conversation))
        ->assertRedirect(route('communication.conversations.show', $conversation));

    $this->actingAs($outsider)
        ->get(route('communication.conversations.show', $conversation))
        ->assertForbidden();

    $this->actingAs($recipient)
        ->get(route('communication.conversations.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('communication/Index')
            ->where('activeConversation.id', $conversation->id)
            ->has('privateMessages', 1)
            ->where('privateMessages.0.message', 'Mensaje previo')
        );

    $this->actingAs($recipient)
        ->post(route('communication.conversations.reply', $conversation), [
            'message' => 'Recibí tu mensaje.',
        ])
        ->assertRedirect(route('communication.conversations.show', $conversation));

    $this->assertDatabaseHas('private_messages', [
        'conversation_id' => $conversation->id,
        'sender_id' => $recipient->id,
        'recipient_id' => $sender->id,
        'message' => 'Recibí tu mensaje.',
    ]);

    $this->actingAs($outsider)
        ->post(route('communication.conversations.reply', $conversation), [
            'message' => 'No autorizado',
        ])
        ->assertForbidden();
});

test('users cannot initiate a private conversation with themselves', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('private-chat.store'), [
            'recipient_id' => $user->id,
            'message' => 'Mensaje propio',
        ])
        ->assertSessionHasErrors('recipient_id');

    $this->assertDatabaseCount('conversations', 0);
    $this->assertDatabaseCount('private_messages', 0);
});
