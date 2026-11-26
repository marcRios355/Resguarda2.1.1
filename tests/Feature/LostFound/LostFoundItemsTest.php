<?php

use App\Models\LostFoundItem;
use App\Models\User;
use App\Services\PrivateImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function lostFoundItemPayload(array $overrides = []): array
{
    return [
        'type' => 'lost',
        'category' => 'keys',
        'title' => 'Llaves encontradas cerca del parque',
        'description' => 'Un llavero azul con tres llaves.',
        'event_date' => today()->subDay()->toDateString(),
        'approximate_zone' => 'Parque Central',
        ...$overrides,
    ];
}

test('authenticated users can publish a lost or found item with a private photo', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/u2QAAAAASUVORK5CYII=', true);
    $exifData = 'private location metadata';
    $exifChunk = pack('N', strlen($exifData)).'eXIf'.$exifData.pack('N', crc32('eXIf'.$exifData));
    $photo = UploadedFile::fake()->createWithContent('llavero.png', substr($png, 0, 33).$exifChunk.substr($png, 33));

    $response = $this->actingAs($user)->post(route('lost-found.store'), [
        ...lostFoundItemPayload(['type' => 'found']),
        'photo' => $photo,
    ]);

    $item = LostFoundItem::query()->sole();

    $response->assertRedirect(route('lost-found.show', $item));
    $this->assertDatabaseHas('lost_found_items', [
        'id' => $item->id,
        'user_id' => $user->id,
        'type' => 'found',
        'category' => 'keys',
        'status' => 'open',
    ]);
    $storedPhoto = app(PrivateImageStorage::class)->contents($item->photo_path);
    expect($storedPhoto)->not->toBeNull()
        ->and($storedPhoto)->not->toContain('eXIf')
        ->and(getimagesizefromstring($storedPhoto))->not->toBeFalse();

    $this->actingAs($user)
        ->get(route('lost-found.show', $item))
        ->assertInertia(fn ($page) => $page
            ->hasFlash('status', 'Publicación creada correctamente.')
            ->where('item.is_owner', true));

    $this->actingAs($user)
        ->get(route('lost-found.photo', $item))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'max-age=3600, private');
});

test('the listing can be filtered by type and category without exposing owner contact data', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $matchingItem = LostFoundItem::create([
        ...lostFoundItemPayload(['type' => 'found']),
        'user_id' => $owner->id,
        'status' => 'open',
    ]);
    LostFoundItem::create([
        ...lostFoundItemPayload(['type' => 'lost', 'category' => 'electronics', 'title' => 'Audífonos']),
        'user_id' => $otherOwner->id,
        'status' => 'open',
    ]);

    $response = $this->actingAs($otherOwner)->get(route('lost-found.index', [
        'type' => 'found',
        'category' => 'keys',
    ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('lost-found/Index')
            ->has('items.data', 1)
            ->where('items.data.0.id', $matchingItem->id)
            ->where('items.data.0.is_owner', false)
            ->missing('items.data.0.user')
            ->missing('items.data.0.email'));
});

test('the owner can update and delete their own listing', function () {
    $owner = User::factory()->create();
    $item = LostFoundItem::create([
        ...lostFoundItemPayload(),
        'user_id' => $owner->id,
        'status' => 'open',
    ]);

    $this->actingAs($owner)
        ->put(route('lost-found.update', $item), lostFoundItemPayload([
            'type' => 'found',
            'status' => 'resolved',
            'title' => 'Llavero recuperado',
        ]))
        ->assertRedirect(route('lost-found.show', $item));

    $this->assertDatabaseHas('lost_found_items', [
        'id' => $item->id,
        'type' => 'found',
        'status' => 'resolved',
        'title' => 'Llavero recuperado',
    ]);

    $this->actingAs($owner)
        ->delete(route('lost-found.destroy', $item))
        ->assertRedirect(route('lost-found.index'));

    $this->assertDatabaseMissing('lost_found_items', ['id' => $item->id]);
});

test('another user cannot update or delete a listing', function (string $method) {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $item = LostFoundItem::create([
        ...lostFoundItemPayload(),
        'user_id' => $owner->id,
        'status' => 'open',
    ]);

    $response = $method === 'put'
        ? $this->actingAs($otherUser)->put(route('lost-found.update', $item), lostFoundItemPayload())
        : $this->actingAs($otherUser)->delete(route('lost-found.destroy', $item));

    $response->assertForbidden();
    $this->assertModelExists($item);
})->with([
    'update' => 'put',
    'delete' => 'delete',
]);

test('listing text rejects contact details and private house addresses', function (string $field, string $value) {
    $user = User::factory()->create();
    $payload = lostFoundItemPayload([$field => $value]);

    $response = $this->actingAs($user)->post(route('lost-found.store'), $payload);

    $response->assertSessionHasErrors($field);
    $this->assertDatabaseCount('lost_found_items', 0);
})->with([
    'email in description' => ['description', 'Contacta a persona@example.com para coordinar.'],
    'phone in title' => ['title', 'Llaves 987654321'],
    'house number in approximate zone' => ['approximate_zone', 'Domicilio 123'],
]);

test('invalid listing category and future event dates are rejected', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('lost-found.store'), lostFoundItemPayload([
        'category' => 'unlisted',
        'event_date' => today()->addDay()->toDateString(),
    ]));

    $response->assertSessionHasErrors(['category', 'event_date']);
    $this->assertDatabaseCount('lost_found_items', 0);
});

test('lost and found pages require authentication', function () {
    $item = LostFoundItem::create([
        ...lostFoundItemPayload(),
        'user_id' => User::factory()->create()->id,
        'status' => 'open',
    ]);

    $this->get(route('lost-found.index'))->assertRedirect(route('login'));
    $this->get(route('lost-found.show', $item))->assertRedirect(route('login'));
    $this->get(route('lost-found.photo', $item))->assertRedirect(route('login'));
});
