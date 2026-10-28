<?php

namespace App\Http\Controllers;

use App\Models\CommunityMessage;
use App\Models\Conversation;
use App\Models\District;
use App\Models\PrivateMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
        $messages = CommunityMessage::with(['user', 'district'])
            ->where('is_hidden', false)
            ->when($filters['district_id'] ?? null, fn (Builder $query, int $districtId) => $query->where('district_id', $districtId))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (CommunityMessage $message): array => $this->communityMessageData($message))
            ->all();

        $conversations = Conversation::with(['userOne', 'userTwo', 'messages' => fn ($query) => $query->latest()->limit(1)])
            ->where(fn (Builder $query) => $query->where('user_one_id', $userId)->orWhere('user_two_id', $userId))
            ->latest('last_message_at')
            ->get()
            ->map(function (Conversation $conversation) use ($userId): array {
                $otherUser = $conversation->otherUser($userId);

                return [
                    'id' => $conversation->id,
                    'other_user_name' => $otherUser->name,
                    'last_message' => $conversation->messages->first()?->message,
                    'last_message_at' => $conversation->last_message_at?->format('Y-m-d H:i:s'),
                ];
            })
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
        $messages = $conversation->messages()
            ->with('sender:id,name')
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
     * @return array<string, int|string|null>
     */
    private function communityMessageData(CommunityMessage $message): array
    {
        return [
            'id' => $message->id,
            'user_name' => $message->user?->name,
            'district_name' => $message->district?->name,
            'message' => $message->message,
            'created_at' => $message->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
