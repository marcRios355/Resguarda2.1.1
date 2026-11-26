<?php

use App\Models\CommunityMessage;
use App\Models\Conversation;
use App\Models\PrivateMessage;
use App\Models\User;
use App\Services\PrivateImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function chatPng(string $exifData = ''): UploadedFile
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/u2QAAAAASUVORK5CYII=', true);
    $exifChunk = $exifData === '' ? '' : pack('N', strlen($exifData)).'eXIf'.$exifData.pack('N', crc32('eXIf'.$exifData));

    return UploadedFile::fake()->createWithContent('foto.png', substr($png, 0, 33).$exifChunk.substr($png, 33));
}

function chatConversation(User $first, User $second): Conversation
{
    return Conversation::create([
        'user_one_id' => min($first->id, $second->id),
        'user_two_id' => max($first->id, $second->id),
        'last_message_at' => now(),
    ]);
}

beforeEach(function () {
    Storage::fake('local');
});

test('users can publish a community photo with text and metadata is removed', function () {
    $author = User::factory()->create();
    $reader = User::factory()->create();

    $this->actingAs($author)
        ->post(route('chat.store'), [
            'message' => 'Calle inundada en el centro',
            'photo' => chatPng('GPS -8.11,-79.02'),
        ])
        ->assertRedirect(route('communication.index', ['tab' => 'community']));

    $message = CommunityMessage::query()->sole();
    expect($message->attachment_path)->not->toBeNull()
        ->and(app(PrivateImageStorage::class)->contents($message->attachment_path))->not->toContain('GPS');

    $this->actingAs($reader)
        ->get(route('communication.index', ['tab' => 'community']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('communityMessages.0.photo_url', route('chat.photo', $message, false))
            ->where('communityMessages.0.message', 'Calle inundada en el centro'));

    $this->actingAs($reader)
        ->get(route('chat.photo', $message))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('a community photo can be sent without text but an empty message is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('chat.store'), ['photo' => chatPng()])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('chat.store'), ['message' => ''])
        ->assertSessionHasErrors(['message', 'photo']);

    expect(CommunityMessage::query()->count())->toBe(1);
});

test('files that are not real images are rejected by the backend', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('chat.store'), [
            'message' => 'Archivo falso',
            'photo' => UploadedFile::fake()->createWithContent('foto.png', '<?php echo "x";'),
        ])
        ->assertSessionHasErrors('photo');

    $this->actingAs($user)
        ->post(route('chat.store'), [
            'message' => 'Muy grande',
            'photo' => UploadedFile::fake()->createWithContent('grande.png', chatPng()->getContent().str_repeat('0', 9 * 1024 * 1024)),
        ])
        ->assertSessionHasErrors('photo');

    expect(CommunityMessage::query()->count())->toBe(0);
});

test('photos of hidden community messages are not served', function () {
    $user = User::factory()->create();
    $message = CommunityMessage::create([
        'user_id' => $user->id,
        'message' => 'Oculto',
        'attachment_path' => 'chat/community/oculta.png',
        'is_hidden' => true,
    ]);
    Storage::disk('local')->put('chat/community/oculta.png', 'x');

    $this->actingAs(User::factory()->create())
        ->get(route('chat.photo', $message))
        ->assertNotFound();
});

test('guests cannot open chat photos', function () {
    $message = CommunityMessage::create([
        'user_id' => User::factory()->create()->id,
        'message' => '',
        'attachment_path' => 'chat/community/foto.png',
    ]);

    $this->get(route('chat.photo', $message))->assertRedirect(route('login'));
});

test('participants can send and view private photos while other users cannot', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $outsider = User::factory()->create();
    $conversation = chatConversation($sender, $recipient);

    $this->actingAs($sender)
        ->post(route('communication.conversations.reply', $conversation), [
            'photo' => chatPng(),
        ])
        ->assertRedirect(route('communication.conversations.show', $conversation));

    $message = PrivateMessage::query()->sole();
    expect($message->attachment_path)->toStartWith('chat/private/'.$conversation->id.'/')
        ->and($message->message)->toBe('');

    $this->actingAs($recipient)
        ->get(route('communication.messages.photo', $message))
        ->assertOk();

    $this->actingAs($sender)
        ->get(route('communication.messages.photo', $message))
        ->assertOk();

    $this->actingAs($outsider)
        ->get(route('communication.messages.photo', $message))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->post(route('communication.conversations.reply', $conversation), ['photo' => chatPng()])
        ->assertForbidden();

    $this->actingAs($recipient)
        ->get(route('communication.conversations.show', $conversation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('privateMessages.0.photo_url', route('communication.messages.photo', $message, false)));
});
