<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\PrivateMessage;
use App\Services\PrivateImageStorage;
use App\Services\PrivateMessenger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateChatController extends Controller
{
    public function __construct(
        private PrivateImageStorage $images,
        private PrivateMessenger $messenger,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('communication.index', array_merge(
            ['tab' => 'private'],
            $request->only('search'),
        ));
    }

    public function show(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($conversation->hasParticipant($request->user()->id), 403);

        return redirect()->route('communication.conversations.show', $conversation);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id', 'not_in:'.$request->user()->id],
            ...PrivateMessenger::rules(),
        ], PrivateMessenger::ruleMessages());

        $recipientId = (int) $validated['recipient_id'];
        $conversation = $this->messenger->conversationBetween($request->user()->id, $recipientId);

        $this->sendMessage($request, $conversation, $recipientId, $validated['message'] ?? null);

        return redirect()->route('communication.index', ['tab' => 'private'])
            ->with('status', 'Mensaje privado enviado.');
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        $userId = $request->user()->id;
        abort_unless($conversation->hasParticipant($userId), 403);

        $validated = $request->validate(PrivateMessenger::rules(), PrivateMessenger::ruleMessages());

        $recipientId = $conversation->otherUser($userId)->id;
        $this->sendMessage($request, $conversation, $recipientId, $validated['message'] ?? null);

        return redirect()->route('communication.conversations.show', $conversation)
            ->with('status', 'Mensaje privado enviado.');
    }

    /**
     * Solo los dos participantes de la conversación pueden ver sus fotografías.
     */
    public function photo(Request $request, PrivateMessage $privateMessage): StreamedResponse
    {
        $userId = $request->user()->id;
        abort_unless(in_array($userId, [$privateMessage->sender_id, $privateMessage->recipient_id], true), 403);

        return $this->images->response($privateMessage->attachment_path);
    }

    private function sendMessage(Request $request, Conversation $conversation, int $recipientId, ?string $text): void
    {
        $photo = $request->file('photo');

        $this->messenger->send($request->user(), $conversation, $recipientId, $text, $photo);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $photo !== null ? 'Fotografía enviada.' : 'Mensaje privado enviado.',
        ]);
    }
}
