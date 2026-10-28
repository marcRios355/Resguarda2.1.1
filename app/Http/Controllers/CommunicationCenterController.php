<?php

namespace App\Http\Controllers;

use App\Models\CommunityMessage;
use App\Models\Conversation;
use App\Models\District;
use App\Models\PrivateMessage;
use App\Models\User;
use App\Notifications\PrivateMessageReceived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationCenterController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['community', 'private'])],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $userId = $request->user()->id;
        $search = trim($filters['search'] ?? '');
        $messages = CommunityMessage::with(['user:id,name,username', 'district:id,name', 'replyTo.user:id,name'])
            ->where('is_hidden', false)
            ->when($filters['district_id'] ?? null, fn (Builder $query, int $districtId) => $query->where('district_id', $districtId))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (CommunityMessage $message): array => $this->communityMessageData($message, $userId))
            ->all();

        $conversations = Conversation::with(['userOne', 'userTwo'])
            ->withCount(['messages as unread_count' => fn (Builder $query) => $query->where('recipient_id', $userId)->whereNull('read_at')])
            ->where(fn (Builder $query) => $query->where('user_one_id', $userId)->orWhere('user_two_id', $userId))
            ->latest('last_message_at')
            ->get();
        $lastMessages = PrivateMessage::query()
            ->whereIn('id', PrivateMessage::query()
                ->selectRaw('max(id)')
                ->whereIn('conversation_id', $conversations->pluck('id'))
                ->groupBy('conversation_id'))
            ->get()
            ->keyBy('conversation_id');
        $conversations = $conversations
            ->map(function (Conversation $conversation) use ($userId, $lastMessages): array {
                $otherUser = $conversation->otherUser($userId);
                $lastMessage = $lastMessages->get($conversation->id);

                return [
                    'id' => $conversation->id,
                    'other_user_name' => $otherUser->name,
                    'last_message' => $lastMessage === null ? null : ($lastMessage->message !== '' ? $lastMessage->message : 'Fotografía'),
                    'last_message_has_photo' => $lastMessage?->attachment_path !== null,
                    'last_message_at' => $conversation->last_message_at?->format('Y-m-d H:i:s'),
                    'unread_count' => (int) $conversation->unread_count,
                ];
            })
            ->values()
            ->all();

        $users = User::query()
            ->where('id', '!=', $userId)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'username', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
            ])
            ->all();

        return Inertia::render('communication/Index', [
            'tab' => $filters['tab'] ?? 'community',
            'districts' => District::query()->orderBy('name')->get(['id', 'name']),
            'selectedDistrictId' => isset($filters['district_id']) ? (int) $filters['district_id'] : null,
            'communityMessages' => $messages,
            'conversations' => $conversations,
            'users' => $users,
            'search' => $search,
            'activeConversation' => null,
            'privateMessages' => [],
        ]);
    }

    public function conversation(Request $request, Conversation $conversation): Response
    {
        $userId = $request->user()->id;
        abort_unless(in_array($userId, [$conversation->user_one_id, $conversation->user_two_id], true), 403);

        $otherUser = $conversation->otherUser($userId);

        $conversation->messages()
            ->where('recipient_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        $request->user()->unreadNotifications()
            ->where('type', PrivateMessageReceived::class)
            ->get()
            ->filter(fn (DatabaseNotification $notification): bool => (int) ($notification->data['conversation_id'] ?? 0) === $conversation->id)
            ->each(fn (DatabaseNotification $notification) => $notification->markAsRead());

        $messages = $conversation->messages()
            ->with(['sender:id,name', 'lostFoundItem:id,title,type'])
            ->latest()
            ->limit(100)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (PrivateMessage $message): array => [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender?->name,
                'message' => $message->message,
                'photo_url' => $message->attachment_path !== null ? route('communication.messages.photo', $message, false) : null,
                'lost_found_item' => $message->lostFoundItem !== null ? [
                    'id' => $message->lostFoundItem->id,
                    'title' => $message->lostFoundItem->title,
                    'type' => $message->lostFoundItem->type,
                ] : null,
                'read_at' => $message->read_at?->format('Y-m-d H:i:s'),
                'created_at' => $message->created_at?->format('Y-m-d H:i:s'),
            ])
            ->all();

        return Inertia::render('communication/Index', [
            'tab' => 'private',
            'districts' => [],
            'selectedDistrictId' => null,
            'communityMessages' => [],
            'conversations' => [],
            'users' => [],
            'search' => '',
            'activeConversation' => [
                'id' => $conversation->id,
                'other_user_id' => $otherUser->id,
                'other_user_name' => $otherUser->name,
            ],
            'privateMessages' => $messages,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function communityMessageData(CommunityMessage $message, int $currentUserId): array
    {
        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'user_name' => $message->user?->name,
            'user_username' => $message->user?->username,
            'is_mine' => $message->user_id === $currentUserId,
            'district_name' => $message->district?->name,
            'message' => $message->message,
            'photo_url' => $message->attachment_path !== null ? route('chat.photo', $message, false) : null,
            'reply_to' => $message->replyTo !== null && ! $message->replyTo->is_hidden ? [
                'id' => $message->replyTo->id,
                'user_name' => $message->replyTo->user?->name,
                'excerpt' => Str::limit($message->replyTo->message !== '' ? $message->replyTo->message : 'Fotografía', 80),
            ] : null,
            'created_at' => $message->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
