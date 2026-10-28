<?php

namespace App\Services;

use App\Models\CommunityMessage;
use App\Models\PrivateMessage;
use App\Models\User;
use App\Notifications\CommunityMessageInteraction;
use App\Notifications\PrivateMessageReceived;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Decide a quién notificar por cada mensaje y evita avisos duplicados.
 */
class MessageNotifier
{
    /**
     * Un único aviso sin leer por conversación: los mensajes siguientes
     * actualizan el aviso existente en lugar de crear uno nuevo.
     */
    public function privateMessageSent(PrivateMessage $message): void
    {
        $message->loadMissing('sender:id,name', 'recipient', 'lostFoundItem:id,title');
        $recipient = $message->recipient;

        if ($recipient === null) {
            return;
        }

        /** @var DatabaseNotification|null $existing */
        $existing = $recipient->unreadNotifications()
            ->where('type', PrivateMessageReceived::class)
            ->get()
            ->first(fn (DatabaseNotification $notification): bool => (int) ($notification->data['conversation_id'] ?? 0) === $message->conversation_id);

        if ($existing !== null) {
            $count = (int) ($existing->data['unread_count'] ?? 1) + 1;
            $existing->forceFill([
                'data' => PrivateMessageReceived::payload($message, $recipient, $count),
                'created_at' => now(),
            ])->save();

            return;
        }

        $recipient->notify(new PrivateMessageReceived($message));
    }

    /**
     * Notifica respuestas y menciones; el resto de la comunidad solo recibe
     * avisos si activó expresamente esa preferencia.
     */
    public function communityMessagePosted(CommunityMessage $message): void
    {
        $message->loadMissing('user:id,name', 'replyTo:id,user_id');

        /** @var array<int, 'reply'|'mention'|'community_message'> $recipients */
        $recipients = [];

        if ($message->replyTo !== null && $message->replyTo->user_id !== $message->user_id) {
            $recipients[$message->replyTo->user_id] = 'reply';
        }

        foreach ($this->mentionedUserIds($message) as $userId) {
            $recipients[$userId] ??= 'mention';
        }

        User::query()
            ->where('notify_all_community_messages', true)
            ->whereKeyNot($message->user_id)
            ->pluck('id')
            ->each(function (int $userId) use (&$recipients): void {
                $recipients[$userId] ??= 'community_message';
            });

        if ($recipients === []) {
            return;
        }

        User::query()
            ->whereKey(array_keys($recipients))
            ->get()
            ->each(fn (User $user) => $user->notify(new CommunityMessageInteraction($message, $recipients[$user->id])));
    }

    /**
     * @return list<int>
     */
    private function mentionedUserIds(CommunityMessage $message): array
    {
        preg_match_all('/(?<![\w@])@([A-Za-z0-9_.\-]{3,50})/u', $message->message, $matches);
        $usernames = array_values(array_unique(array_map(fn (string $name): string => rtrim($name, '.'), $matches[1])));

        if ($usernames === []) {
            return [];
        }

        return User::query()
            ->whereIn('username', $usernames)
            ->whereKeyNot($message->user_id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
