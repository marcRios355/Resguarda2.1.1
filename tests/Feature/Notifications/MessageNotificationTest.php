<?php

use App\Models\CommunityMessage;
use App\Models\Conversation;
use App\Models\PrivateMessage;
use App\Models\User;
use App\Notifications\CommunityMessageInteraction;
use App\Notifications\PrivateMessageReceived;
use Inertia\Testing\AssertableInertia as Assert;

function notificationConversation(User $first, User $second): Conversation
{
    return Conversation::create([
        'user_one_id' => min($first->id, $second->id),
        'user_two_id' => max($first->id, $second->id),
        'last_message_at' => now(),
    ]);
}

test('recipients get one stored notification per conversation and it groups new messages', function () {
    $sender = User::factory()->create(['name' => 'Ana']);
    $recipient = User::factory()->create();
    $conversation = notificationConversation($sender, $recipient);

    $this->actingAs($sender)->post(route('communication.conversations.reply', $conversation), ['message' => 'Hola, ¿estás bien?']);
    $this->actingAs($sender)->post(route('communication.conversations.reply', $conversation), ['message' => '¿Llegaste al refugio?']);

    $notifications = $recipient->fresh()->unreadNotifications;
    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->type)->toBe(PrivateMessageReceived::class)
        ->and($notifications->first()->data['title'])->toBe('2 mensajes nuevos de Ana')
        ->and($notifications->first()->data['body'])->toBe('¿Llegaste al refugio?')
        ->and($sender->fresh()->notifications)->toHaveCount(0);

    $this->actingAs($recipient)
        ->getJson(route('notifications.summary'))
        ->assertOk()
        ->assertJsonPath('unread_notifications', 1)
        ->assertJsonPath('unread_private_messages', 2);
});

test('opening a conversation marks its messages and notification as read', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = notificationConversation($sender, $recipient);

    $this->actingAs($sender)->post(route('communication.conversations.reply', $conversation), ['message' => 'Mensaje']);

    $this->actingAs($recipient)
        ->get(route('communication.index', ['tab' => 'private']))
        ->assertInertia(fn (Assert $page) => $page->where('conversations.0.unread_count', 1));

    $this->actingAs($recipient)->get(route('communication.conversations.show', $conversation))->assertOk();

    expect(PrivateMessage::query()->whereNull('read_at')->count())->toBe(0)
        ->and($recipient->fresh()->unreadNotifications)->toHaveCount(0);
});

test('message previews are hidden when the recipient disables them', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create(['show_message_previews' => false]);
    $conversation = notificationConversation($sender, $recipient);

    $this->actingAs($sender)->post(route('communication.conversations.reply', $conversation), ['message' => 'Contenido privado']);

    expect($recipient->fresh()->notifications->first()->data['body'])->toBe('Tienes un nuevo mensaje privado.');
});

test('replies and mentions notify only the involved users once', function () {
    $author = User::factory()->create(['username' => 'ana']);
    $mentioned = User::factory()->create(['username' => 'bruno']);
    $bystander = User::factory()->create(['username' => 'carla']);
    $original = CommunityMessage::create(['user_id' => $author->id, 'message' => 'Hay corte de agua']);

    $this->actingAs($mentioned)->post(route('chat.store'), [
        'message' => '@ana confirmo, también en mi calle',
        'reply_to_id' => $original->id,
    ]);

    $this->actingAs($bystander)->post(route('chat.store'), [
        'message' => 'Gracias @bruno y @bruno',
    ]);

    expect($author->fresh()->notifications)->toHaveCount(1)
        ->and($author->fresh()->notifications->first()->data['kind'])->toBe('community_reply')
        ->and($mentioned->fresh()->notifications)->toHaveCount(1)
        ->and($mentioned->fresh()->notifications->first()->data['kind'])->toBe('community_mention')
        ->and($bystander->fresh()->notifications)->toHaveCount(0);
});

test('community messages only notify everyone who opted in', function () {
    $author = User::factory()->create();
    $optedIn = User::factory()->create(['notify_all_community_messages' => true]);
    $defaultUser = User::factory()->create();

    $this->actingAs($author)->post(route('chat.store'), ['message' => 'Aviso general']);

    expect($optedIn->fresh()->notifications)->toHaveCount(1)
        ->and($optedIn->fresh()->notifications->first()->type)->toBe(CommunityMessageInteraction::class)
        ->and($defaultUser->fresh()->notifications)->toHaveCount(0)
        ->and($author->fresh()->notifications)->toHaveCount(0);
});

test('users can read notifications, mark all read and update preferences', function () {
    $this->withoutVite();
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = notificationConversation($sender, $recipient);
    $this->actingAs($sender)->post(route('communication.conversations.reply', $conversation), ['message' => 'Hola']);
    $notification = $recipient->fresh()->notifications->first();

    $this->actingAs($recipient)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/Index')
            ->has('notifications', 1)
            ->where('notifications.0.read_at', null));

    $this->actingAs($recipient)
        ->post(route('notifications.read', $notification->id))
        ->assertRedirect(route('communication.conversations.show', $conversation, false));

    expect($notification->fresh()->read_at)->not->toBeNull();

    $this->actingAs($sender)
        ->post(route('notifications.read', $notification->id))
        ->assertNotFound();

    $this->actingAs($recipient)
        ->patch(route('notifications.preferences'), [
            'notify_all_community_messages' => true,
            'show_message_previews' => false,
        ])
        ->assertRedirect(route('notifications.index'));

    expect($recipient->fresh())
        ->notify_all_community_messages->toBeTrue()
        ->show_message_previews->toBeFalse();

    $this->actingAs($recipient)->post(route('notifications.read-all'))->assertRedirect(route('notifications.index'));
});
