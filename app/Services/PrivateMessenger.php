<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\PrivateMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Envía mensajes privados (texto y/o fotografía) y notifica al destinatario.
 * Lo usan el centro de comunicación y el contacto desde objetos perdidos.
 */
class PrivateMessenger
{
    public function __construct(
        private PrivateImageStorage $images,
        private MessageNotifier $notifier,
    ) {}

    public function conversationBetween(int $userId, int $otherUserId): Conversation
    {
        return Conversation::firstOrCreate(
            [
                'user_one_id' => min($userId, $otherUserId),
                'user_two_id' => max($userId, $otherUserId),
            ],
            ['last_message_at' => now()],
        );
    }

    public function send(
        User $sender,
        Conversation $conversation,
        int $recipientId,
        ?string $text,
        ?UploadedFile $photo = null,
        ?int $lostFoundItemId = null,
    ): PrivateMessage {
        $photoPath = $photo !== null
            ? $this->images->store($photo, 'chat/private/'.$conversation->id)
            : null;

        try {
            $privateMessage = DB::transaction(function () use ($sender, $conversation, $recipientId, $text, $photoPath, $lostFoundItemId): PrivateMessage {
                $privateMessage = PrivateMessage::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'recipient_id' => $recipientId,
                    'lost_found_item_id' => $lostFoundItemId,
                    'message' => trim($text ?? ''),
                    'attachment_path' => $photoPath,
                ]);

                $conversation->last_message_at = $privateMessage->created_at;
                $conversation->save();

                return $privateMessage;
            });
        } catch (Throwable $exception) {
            $this->images->delete($photoPath);

            throw $exception;
        }

        $this->notifier->privateMessageSent($privateMessage);

        return $privateMessage;
    }

    /**
     * Reglas compartidas: se exige texto o fotografía.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'message' => ['nullable', 'required_without:photo', 'string', 'max:1000'],
            'photo' => ['nullable', 'required_without:message', ...PrivateImageStorage::rules()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function ruleMessages(): array
    {
        return [
            'message.required_without' => 'Escribe un mensaje o adjunta una fotografía.',
            'photo.required_without' => 'Escribe un mensaje o adjunta una fotografía.',
        ];
    }
}
