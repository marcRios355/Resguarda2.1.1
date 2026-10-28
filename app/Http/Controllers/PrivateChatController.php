<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\PrivateMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrivateChatController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('communication.index', array_merge(
            ['tab' => 'private'],
            $request->only('search'),
        ));
    }

    public function show(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless(in_array($request->user()->id, [$conversation->user_one_id, $conversation->user_two_id], true), 403);

        return redirect()->route('communication.conversations.show', $conversation);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id', 'not_in:'.$request->user()->id],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $recipientId = (int) $validated['recipient_id'];
        $userId = $request->user()->id;

        $conversation = Conversation::firstOrCreate(
            [
                'user_one_id' => min($userId, $recipientId),
                'user_two_id' => max($userId, $recipientId),
            ],
            ['last_message_at' => now()],
        );

        $privateMessage = PrivateMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userId,
            'recipient_id' => $recipientId,
            'message' => $validated['message'],
        ]);

        $conversation->last_message_at = $privateMessage->created_at;
        $conversation->save();

        return redirect()->route('communication.index', ['tab' => 'private'])
            ->with('status', 'Mensaje privado enviado.');
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        $userId = $request->user()->id;
        abort_unless(in_array($userId, [$conversation->user_one_id, $conversation->user_two_id], true), 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $recipientId = $conversation->otherUser($userId)->id;
        $privateMessage = PrivateMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userId,
            'recipient_id' => $recipientId,
            'message' => $validated['message'],
        ]);

        $conversation->last_message_at = $privateMessage->created_at;
        $conversation->save();

        return redirect()->route('communication.conversations.show', $conversation)
            ->with('status', 'Mensaje privado enviado.');
    }
}
