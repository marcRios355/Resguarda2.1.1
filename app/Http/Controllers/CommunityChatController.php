<?php

namespace App\Http\Controllers;

use App\Models\CommunityMessage;
use App\Services\MessageNotifier;
use App\Services\PrivateImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CommunityChatController extends Controller
{
    public function __construct(private PrivateImageStorage $images) {}

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('communication.index', array_merge(
            ['tab' => 'community'],
            $request->only('district_id'),
        ));
    }

    public function store(Request $request, MessageNotifier $notifier): RedirectResponse
    {
        $validated = $request->validate([
            'district_id' => ['nullable', 'exists:districts,id'],
            'reply_to_id' => ['nullable', 'integer', 'exists:community_messages,id'],
            'message' => ['nullable', 'required_without:photo', 'string', 'max:1000'],
            'photo' => ['nullable', 'required_without:message', ...PrivateImageStorage::rules()],
        ], [
            'message.required_without' => 'Escribe un mensaje o adjunta una fotografía.',
            'photo.required_without' => 'Escribe un mensaje o adjunta una fotografía.',
        ]);

        $photoPath = $request->hasFile('photo')
            ? $this->images->store($request->file('photo'), 'chat/community')
            : null;

        try {
            $message = CommunityMessage::create([
                'user_id' => $request->user()->id,
                'district_id' => $validated['district_id'] ?? null,
                'reply_to_id' => $validated['reply_to_id'] ?? null,
                'message' => trim($validated['message'] ?? ''),
                'attachment_path' => $photoPath,
                'is_reported' => false,
                'report_count' => 0,
                'is_hidden' => false,
            ]);
        } catch (Throwable $exception) {
            $this->images->delete($photoPath);

            throw $exception;
        }

        $notifier->communityMessagePosted($message);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $photoPath !== null ? 'Fotografía publicada en la comunidad.' : 'Mensaje publicado en la comunidad.',
        ]);

        return redirect()->route('communication.index', array_filter([
            'tab' => 'community',
            'district_id' => $request->input('filter_district_id'),
        ]))->with('status', 'Mensaje publicado en el chat comunitario.');
    }

    /**
     * Las fotografías del chat comunitario solo se entregan a usuarios con
     * sesión iniciada y nunca si el mensaje fue ocultado por moderación.
     */
    public function photo(Request $request, CommunityMessage $communityMessage): StreamedResponse
    {
        abort_if($communityMessage->is_hidden && ! $request->user()->isAdmin(), 404);

        return $this->images->response($communityMessage->attachment_path);
    }
}
