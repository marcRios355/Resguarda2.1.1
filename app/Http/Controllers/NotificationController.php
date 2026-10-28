<?php

namespace App\Http\Controllers;

use App\Models\PrivateMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('notifications/Index', [
            'notifications' => $user->notifications()
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (DatabaseNotification $notification): array => $this->notificationData($notification))
                ->all(),
            'preferences' => [
                'notify_all_community_messages' => (bool) $user->notify_all_community_messages,
                'show_message_previews' => (bool) $user->show_message_previews,
            ],
        ]);
    }

    /**
     * Resumen ligero que el cliente consulta periódicamente para actualizar
     * contadores y mostrar avisos del navegador sin recargar la página.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            ...self::counts($user),
            'latest' => $user->unreadNotifications()
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (DatabaseNotification $notification): array => $this->notificationData($notification))
                ->all(),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Marca el aviso como leído y lleva al usuario al contenido que lo generó.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        $url = $record->data['url'] ?? null;

        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Todas las notificaciones se marcaron como leídas.']);

        return redirect()->route('notifications.index');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notify_all_community_messages' => ['required', 'boolean'],
            'show_message_previews' => ['required', 'boolean'],
        ]);

        $request->user()->forceFill($validated)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Preferencias de notificación guardadas.']);

        return redirect()->route('notifications.index');
    }

    /**
     * @return array{unread_notifications: int, unread_private_messages: int}
     */
    public static function counts(User $user): array
    {
        return [
            'unread_notifications' => $user->unreadNotifications()->count(),
            'unread_private_messages' => PrivateMessage::query()
                ->where('recipient_id', $user->id)
                ->whereNull('read_at')
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationData(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'kind' => $notification->data['kind'] ?? 'general',
            'title' => $notification->data['title'] ?? 'Notificación',
            'body' => $notification->data['body'] ?? '',
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
