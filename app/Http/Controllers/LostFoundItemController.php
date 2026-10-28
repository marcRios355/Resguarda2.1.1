<?php

namespace App\Http\Controllers;

use App\Models\LostFoundItem;
use App\Models\PrivateMessage;
use App\Services\PrivateImageStorage;
use App\Services\PrivateMessenger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LostFoundItemController extends Controller
{
    private const TYPES = ['lost', 'found'];

    private const STATUSES = ['open', 'resolved'];

    public function __construct(private PrivateImageStorage $images) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(self::TYPES)],
            'category' => ['nullable', Rule::in(array_keys(LostFoundItem::CATEGORIES))],
        ]);

        $userId = $request->user()->id;
        $items = LostFoundItem::query()
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->latest('created_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (LostFoundItem $item): array => $this->publicItemData($item, $userId));

        return Inertia::render('lost-found/Index', [
            'items' => $items,
            'categories' => LostFoundItem::CATEGORIES,
            'filters' => [
                'type' => $filters['type'] ?? '',
                'category' => $filters['category'] ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->itemRules());
        $photoPath = $this->storePhoto($request);

        try {
            $item = DB::transaction(fn (): LostFoundItem => LostFoundItem::create([
                ...$validated,
                'user_id' => $request->user()->id,
                'photo_path' => $photoPath,
                'status' => 'open',
            ]));
        } catch (Throwable $exception) {
            $this->deletePhoto($photoPath);

            throw $exception;
        }

        Inertia::flash('status', 'Publicación creada correctamente.');

        return redirect()->route('lost-found.show', $item);
    }

    public function show(Request $request, LostFoundItem $lostFoundItem): Response
    {
        $userId = $request->user()->id;
        $isOwner = $lostFoundItem->user_id === $userId;

        // Conversaciones iniciadas desde esta publicación: el dueño ve todas, el
        // resto solo la suya.
        $contacts = PrivateMessage::query()
            ->where('lost_found_item_id', $lostFoundItem->id)
            ->when(! $isOwner, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('sender_id', $userId)->orWhere('recipient_id', $userId)))
            ->with(['sender:id,name', 'recipient:id,name'])
            ->latest()
            ->get()
            ->unique('conversation_id')
            ->map(function (PrivateMessage $message) use ($userId): array {
                $other = $message->sender_id === $userId ? $message->recipient : $message->sender;

                return [
                    'conversation_id' => $message->conversation_id,
                    'other_user_name' => $other?->name,
                    'last_contact_at' => $message->created_at?->format('Y-m-d H:i'),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('lost-found/Show', [
            'item' => $this->publicItemData($lostFoundItem, $userId),
            'contacts' => $contacts,
            'categories' => LostFoundItem::CATEGORIES,
            'types' => [
                'lost' => 'Perdido',
                'found' => 'Encontrado',
            ],
            'statuses' => [
                'open' => 'Activo',
                'resolved' => 'Resuelto',
            ],
        ]);
    }

    public function photo(LostFoundItem $lostFoundItem): StreamedResponse
    {
        return $this->images->response($lostFoundItem->photo_path);
    }

    /**
     * Abre (o continúa) una conversación privada entre quien encontró o
     * reconoce el objeto y la persona que lo publicó.
     */
    public function contact(Request $request, LostFoundItem $lostFoundItem, PrivateMessenger $messenger): RedirectResponse
    {
        $user = $request->user();
        abort_if($lostFoundItem->user_id === $user->id, 403);

        if ($lostFoundItem->status !== 'open') {
            throw ValidationException::withMessages([
                'message' => 'Esta publicación ya fue marcada como resuelta.',
            ]);
        }

        $validated = $request->validate(PrivateMessenger::rules(), PrivateMessenger::ruleMessages());

        $conversation = $messenger->conversationBetween($user->id, $lostFoundItem->user_id);
        $messenger->send(
            $user,
            $conversation,
            $lostFoundItem->user_id,
            $validated['message'] ?? null,
            $request->file('photo'),
            $lostFoundItem->id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Mensaje enviado a quien publicó el aviso.',
        ]);

        return redirect()->route('communication.conversations.show', $conversation);
    }

    public function update(Request $request, LostFoundItem $lostFoundItem): RedirectResponse
    {
        $this->authorizeOwner($request, $lostFoundItem);
        $validated = $request->validate([
            ...$this->itemRules(),
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $newPhotoPath = $this->storePhoto($request);
        $oldPhotoPath = $lostFoundItem->photo_path;

        try {
            DB::transaction(fn () => $lostFoundItem->update([
                ...$validated,
                'photo_path' => $newPhotoPath ?? $oldPhotoPath,
            ]));
        } catch (Throwable $exception) {
            $this->deletePhoto($newPhotoPath);

            throw $exception;
        }

        if ($newPhotoPath !== null) {
            $this->deletePhoto($oldPhotoPath);
        }

        Inertia::flash('status', 'Publicación actualizada correctamente.');

        return redirect()->route('lost-found.show', $lostFoundItem);
    }

    public function destroy(Request $request, LostFoundItem $lostFoundItem): RedirectResponse
    {
        $this->authorizeOwner($request, $lostFoundItem);
        $photoPath = $lostFoundItem->photo_path;

        DB::transaction(fn () => $lostFoundItem->delete());
        $this->deletePhoto($photoPath);

        Inertia::flash('status', 'Publicación eliminada correctamente.');

        return redirect()->route('lost-found.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function itemRules(): array
    {
        $noPrivateContact = function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $containsEmail = preg_match('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/u', $value) === 1;
            $containsPhone = preg_match('/(?<!\d)(?:\+?\d[\s().-]*){9,15}(?!\d)/u', $value) === 1;
            $containsPrivateAddress = preg_match(
                '/\b(?:casa|domicilio|departamento|dpto|apartamento|apto|interior|int)\.?\s*(?:n(?:umero|ro|[°º.]?)\s*)?#?\d+\b/iu',
                $value,
            ) === 1;

            if ($containsEmail || $containsPhone || $containsPrivateAddress) {
                $fail('No incluyas teléfonos, correos ni direcciones particulares. Usa solo una zona aproximada o referencia pública.');
            }
        };

        return [
            'type' => ['required', Rule::in(self::TYPES)],
            'category' => ['required', Rule::in(array_keys(LostFoundItem::CATEGORIES))],
            'title' => ['required', 'string', 'max:150', $noPrivateContact],
            'description' => ['required', 'string', 'max:2000', $noPrivateContact],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'approximate_zone' => ['required', 'string', 'max:150', $noPrivateContact],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    private function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        return $this->images->store($request->file('photo'), 'lost-found');
    }

    private function deletePhoto(?string $path): void
    {
        $this->images->delete($path);
    }

    private function authorizeOwner(Request $request, LostFoundItem $item): void
    {
        abort_unless($request->user()->id === $item->user_id, 403);
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function publicItemData(LostFoundItem $item, int $userId): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type,
            'category' => $item->category,
            'title' => $item->title,
            'description' => $item->description,
            'event_date' => $item->event_date->toDateString(),
            'approximate_zone' => $item->approximate_zone,
            'photo_url' => $item->photo_path ? route('lost-found.photo', $item) : null,
            'status' => $item->status,
            'created_at' => $item->created_at?->format('Y-m-d H:i'),
            'is_owner' => $item->user_id === $userId,
        ];
    }
}
