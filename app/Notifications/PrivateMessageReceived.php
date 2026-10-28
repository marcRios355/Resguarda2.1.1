<?php

namespace App\Notifications;

use App\Models\PrivateMessage;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class PrivateMessageReceived extends Notification
{
    public function __construct(public PrivateMessage $message, public int $unreadCount = 1) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{kind: string, title: string, body: string, url: string, conversation_id: int, sender_id: int, sender_name: string|null, unread_count: int}
     */
    public function toArray(object $notifiable): array
    {
        return self::payload($this->message, $notifiable, $this->unreadCount);
    }

    /**
     * @return array{kind: string, title: string, body: string, url: string, conversation_id: int, sender_id: int, sender_name: string|null, unread_count: int}
     */
    public static function payload(PrivateMessage $message, object $notifiable, int $unreadCount): array
    {
        $senderName = $message->sender?->name;
        $itemTitle = $message->lostFoundItem?->title;
        $showPreview = ! $notifiable instanceof User || $notifiable->show_message_previews;

        $preview = match (true) {
            ! $showPreview => 'Tienes un nuevo mensaje privado.',
            trim($message->message) !== '' => Str::limit(trim($message->message), 120),
            default => 'Te envió una fotografía.',
        };

        return [
            'kind' => 'private_message',
            'title' => match (true) {
                $itemTitle !== null && $unreadCount === 1 => ($senderName ?? 'Alguien').' te escribió sobre «'.Str::limit($itemTitle, 60).'»',
                $unreadCount > 1 => $unreadCount.' mensajes nuevos de '.($senderName ?? 'un usuario'),
                default => 'Nuevo mensaje de '.($senderName ?? 'un usuario'),
            },
            'body' => $preview,
            'url' => route('communication.conversations.show', $message->conversation_id, false),
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender_name' => $senderName,
            'unread_count' => $unreadCount,
        ];
    }
}
