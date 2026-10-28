<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Guarda fotografías en el disco privado eliminando sus metadatos (EXIF, GPS,
 * XMP) para que no revelen la ubicación ni el dispositivo de quien las envía.
 * Los archivos solo se entregan a través de controladores con autorización.
 */
class PrivateImageStorage
{
    private const DISK = 'local';

    /**
     * Reglas de validación compartidas para fotografías subidas por usuarios.
     *
     * @return list<string>
     */
    public static function rules(int $maxKilobytes = 8192): array
    {
        return [
            'image',
            'mimes:jpg,jpeg,png,webp',
            'mimetypes:image/jpeg,image/png,image/webp',
            'max:'.$maxKilobytes,
            'dimensions:max_width=6000,max_height=6000',
        ];
    }

    public function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, self::DISK);

        if ($path === false) {
            throw new RuntimeException('No fue posible guardar la fotografía.');
        }

        try {
            $this->stripImageMetadata($path);
        } catch (Throwable $exception) {
            $this->delete($path);

            throw $exception;
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path === null) {
            return;
        }

        $disk = Storage::disk(self::DISK);

        if ($disk->exists($path) && ! $disk->delete($path)) {
            throw new RuntimeException('No fue posible retirar una fotografía reemplazada o eliminada.');
        }
    }

    public function response(?string $path): StreamedResponse
    {
        abort_if($path === null, 404);

        $disk = Storage::disk(self::DISK);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function stripImageMetadata(string $path): void
    {
        $disk = Storage::disk(self::DISK);
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
}
