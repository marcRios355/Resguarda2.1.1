<?php

namespace App\Http\Controllers;

use App\Models\LostFoundItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LostFoundItemController extends Controller
{
    private const TYPES = ['lost', 'found'];

    private const STATUSES = ['open', 'resolved'];

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
        return Inertia::render('lost-found/Show', [
            'item' => $this->publicItemData($lostFoundItem, $request->user()->id),
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
        $photoPath = $lostFoundItem->photo_path;
        abort_if($photoPath === null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($photoPath), 404);

        return $disk->response($photoPath, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
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

        $path = $request->file('photo')->store('lost-found', 'local');

        if ($path === false) {
            throw new RuntimeException('No fue posible guardar la fotografía de la publicación.');
        }

        try {
            $this->stripImageMetadata($path);
        } catch (Throwable $exception) {
            $this->deletePhoto($path);

            throw $exception;
        }

        return $path;
    }

    private function stripImageMetadata(string $path): void
    {
        $disk = Storage::disk('local');
        $contents = $disk->get($path);

        $sanitized = match ($disk->mimeType($path)) {
            'image/jpeg' => $this->stripJpegMetadata($contents),
            'image/png' => $this->stripPngMetadata($contents),
            'image/webp' => $this->stripWebpMetadata($contents),
            default => throw new RuntimeException('El formato de la fotografía no está permitido.'),
        };

        if (! $disk->put($path, $sanitized)) {
            throw new RuntimeException('No fue posible proteger los metadatos de la fotografía.');
        }
    }

    private function stripJpegMetadata(string $image): string
    {
        if (! str_starts_with($image, "\xFF\xD8")) {
            throw new RuntimeException('La fotografía JPEG no tiene un formato válido.');
        }

        $sanitized = substr($image, 0, 2);
        $length = strlen($image);
        $offset = 2;

        while ($offset < $length) {
            if (ord($image[$offset]) !== 0xFF) {
                throw new RuntimeException('La fotografía JPEG no tiene un formato válido.');
            }

            $segmentStart = $offset;

            while ($offset < $length && ord($image[$offset]) === 0xFF) {
                $offset++;
            }

            if ($offset >= $length) {
                throw new RuntimeException('La fotografía JPEG está incompleta.');
            }

            $marker = ord($image[$offset++]);

            if ($marker === 0xDA || $marker === 0xD9) {
                return $sanitized.substr($image, $segmentStart);
            }

            if ($marker === 0x00) {
                throw new RuntimeException('La fotografía JPEG no tiene un formato válido.');
            }

            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $sanitized .= substr($image, $segmentStart, $offset - $segmentStart);

                continue;
            }

            if ($offset + 2 > $length) {
                throw new RuntimeException('La fotografía JPEG está incompleta.');
            }

            $segmentLength = $this->unpackLength('n', substr($image, $offset, 2));
            $segmentEnd = $offset + $segmentLength;

            if ($segmentLength < 2 || $segmentEnd > $length) {
                throw new RuntimeException('La fotografía JPEG está incompleta.');
            }

            if (! in_array($marker, [0xE1, 0xED, 0xFE], true)) {
                $sanitized .= substr($image, $segmentStart, $segmentEnd - $segmentStart);
            }

            $offset = $segmentEnd;
        }

        throw new RuntimeException('La fotografía JPEG está incompleta.');
    }

    private function stripPngMetadata(string $image): string
    {
        $signature = "\x89PNG\r\n\x1A\n";

        if (! str_starts_with($image, $signature)) {
            throw new RuntimeException('La fotografía PNG no tiene un formato válido.');
        }

        $sanitized = $signature;
        $length = strlen($image);
        $offset = strlen($signature);
        $hasEndChunk = false;

        while ($offset + 12 <= $length) {
            $chunkStart = $offset;
            $chunkLength = $this->unpackLength('N', substr($image, $offset, 4));
            $chunkEnd = $offset + 12 + $chunkLength;

            if ($chunkEnd > $length) {
                throw new RuntimeException('La fotografía PNG está incompleta.');
            }

            $chunkType = substr($image, $offset + 4, 4);

            if ((ord($chunkType[0]) & 0x20) === 0 || in_array($chunkType, ['tRNS', 'sRGB', 'gAMA', 'cHRM', 'sBIT', 'bKGD'], true)) {
                $sanitized .= substr($image, $chunkStart, $chunkEnd - $chunkStart);
            }

            $offset = $chunkEnd;

            if ($chunkType === 'IEND') {
                $hasEndChunk = true;
                break;
            }
        }

        if (! $hasEndChunk) {
            throw new RuntimeException('La fotografía PNG está incompleta.');
        }

        return $sanitized;
    }

    private function stripWebpMetadata(string $image): string
    {
        if (! str_starts_with($image, 'RIFF') || substr($image, 8, 4) !== 'WEBP') {
            throw new RuntimeException('La fotografía WebP no tiene un formato válido.');
        }

        $chunks = '';
        $length = strlen($image);
        $offset = 12;

        while ($offset + 8 <= $length) {
            $chunkStart = $offset;
            $chunkType = substr($image, $offset, 4);
            $chunkLength = $this->unpackLength('V', substr($image, $offset + 4, 4));
            $chunkEnd = $offset + 8 + $chunkLength + ($chunkLength % 2);

            if ($chunkEnd > $length) {
                throw new RuntimeException('La fotografía WebP está incompleta.');
            }

            if (! in_array($chunkType, ['EXIF', 'XMP ', 'ICCP'], true)) {
                $chunk = substr($image, $chunkStart, $chunkEnd - $chunkStart);

                if ($chunkType === 'VP8X' && $chunkLength >= 10) {
                    $chunk[8] = chr(ord($chunk[8]) & ~0x2C);
                }

                $chunks .= $chunk;
            }

            $offset = $chunkEnd;
        }

        if ($offset !== $length) {
            throw new RuntimeException('La fotografía WebP está incompleta.');
        }

        $body = 'WEBP'.$chunks;

        return 'RIFF'.pack('V', strlen($body)).$body;
    }

    private function unpackLength(string $format, string $bytes): int
    {
        $unpacked = unpack($format, $bytes);

        if ($unpacked === false || ! isset($unpacked[1]) || ! is_int($unpacked[1])) {
            throw new RuntimeException('La fotografía tiene una estructura no válida.');
        }

        return $unpacked[1];
    }

    private function deletePhoto(?string $path): void
    {
        if ($path === null) {
            return;
        }

        $disk = Storage::disk('local');

        if ($disk->exists($path) && ! $disk->delete($path)) {
            throw new RuntimeException('No fue posible retirar una fotografía reemplazada o eliminada.');
        }
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
