<?php

use App\Models\LostFoundItem;
use App\Models\PrivateMessage;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function contactableItem(User $owner, array $overrides = []): LostFoundItem
{
    return LostFoundItem::create([
        'user_id' => $owner->id,
        'type' => 'lost',
        'category' => 'keys',
        'title' => 'Llavero azul',
        'description' => 'Tres llaves con un llavero azul.',
        'event_date' => today()->subDay(),
        'approximate_zone' => 'Plaza de Armas',
        'status' => 'open',
        ...$overrides,
    ]);
}

beforeEach(function () {
    $this->withoutVite();
});

test('a finder can contact the owner and both can continue the conversation', function () {
    $owner = User::factory()->create(['name' => 'Ana']);
    $finder = User::factory()->create(['name' => 'Bruno']);
    $item = contactableItem($owner);

    $response = $this->actingAs($finder)->post(route('lost-found.contact', $item), [
        'message' => '¡Lo encontré cerca del parque!',
    ]);

    $message = PrivateMessage::query()->sole();
    $response->assertRedirect(route('communication.conversations.show', $message->conversation_id));
    expect($message)
        ->sender_id->toBe($finder->id)
        ->recipient_id->toBe($owner->id)
        ->lost_found_item_id->toBe($item->id);

    $notification = $owner->fresh()->unreadNotifications->sole();
    expect($notification->data['title'])->toBe('Bruno te escribió sobre «Llavero azul»');

    $this->actingAs($owner)
        ->get(route('communication.conversations.show', $message->conversation_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('privateMessages.0.lost_found_item.title', 'Llavero azul'));

    $this->actingAs($owner)
        ->post(route('communication.conversations.reply', $message->conversation_id), ['message' => '¡Gracias! ¿Dónde nos vemos?'])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('lost-found.show', $item))
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts', 1)
            ->where('contacts.0.other_user_name', 'Bruno'));
});

test('owners cannot contact themselves and resolved items do not accept contact', function () {
    $owner = User::factory()->create();
    $item = contactableItem($owner);
    $resolved = contactableItem($owner, ['status' => 'resolved']);

    $this->actingAs($owner)
        ->post(route('lost-found.contact', $item), ['message' => 'Hola'])
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->post(route('lost-found.contact', $resolved), ['message' => 'Hola'])
        ->assertSessionHasErrors('message');

    expect(PrivateMessage::query()->count())->toBe(0);
});

test('other users only see their own conversation about an item', function () {
    $owner = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $item = contactableItem($owner, ['type' => 'found']);

    $this->actingAs($first)->post(route('lost-found.contact', $item), ['message' => 'Es mío']);
    $this->actingAs($second)->post(route('lost-found.contact', $item), ['message' => 'Creo que es mío']);

    $this->actingAs($first)
        ->get(route('lost-found.show', $item))
        ->assertInertia(fn (Assert $page) => $page->has('contacts', 1));

    $this->actingAs($owner)
        ->get(route('lost-found.show', $item))
        ->assertInertia(fn (Assert $page) => $page->has('contacts', 2));
});
