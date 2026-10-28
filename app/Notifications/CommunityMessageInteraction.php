<?php

namespace App\Notifications;

use App\Models\CommunityMessage;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class CommunityMessageInteraction extends Notification
{
    /**
     * @param  'reply'|'mention'|'community_message'  $reason
     */
    public function __construct(public CommunityMessage $message, public string $reason) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{kind: string, title: string, body: string, url: string, message_id: int, author_name: string|null}
     */
    public function toArray(object $notifiable): array
    {
        $authorName = $this->message->user->name;
        $showPreview = ! $notifiable instanceof User || $notifiable->show_message_previews;

        $title = match ($this->reason) {
            'reply' => $authorName.' respondió a tu mensaje',
            'mention' => $authorName.' te mencionó en la comunidad',
            default => 'Nuevo mensaje en la comunidad de '.$authorName,
        };

        $body = match (true) {
            ! $showPreview => 'Abre el centro de comunicación para verlo.',
            trim($this->message->message) !== '' => Str::limit(trim($this->message->message), 120),
            default => 'Compartió una fotografía.',
        };

        return [
            'kind' => 'community_'.$this->reason,
            'title' => $title,
            'body' => $body,
            'url' => route('communication.index', ['tab' => 'community'], false).'#mensaje-'.$this->message->id,
            'message_id' => $this->message->id,
            'author_name' => $this->message->user->name,
        ];
    }
}
