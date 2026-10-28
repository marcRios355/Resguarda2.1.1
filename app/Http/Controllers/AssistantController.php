<?php

namespace App\Http\Controllers;

use App\Services\ResguardaAssistant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

class AssistantController extends Controller
{
    public function reply(Request $request, ResguardaAssistant $assistant): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['first_aid', 'infrastructure', 'objects'])],
            'prompt' => ['nullable', 'string', 'max:4000'],
            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:8192',
                'dimensions:max_width=6000,max_height=6000',
            ],
            'messages' => ['nullable', 'array', 'max:12'],
            'messages.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'messages.*.content' => ['required', 'string', 'max:4000'],
        ]);

        $prompt = trim($validated['prompt'] ?? '');
        $image = $request->file('image');
        if ($prompt === '' && $image === null) {
            throw ValidationException::withMessages([
                'prompt' => ['Escribe una pregunta o adjunta una imagen.'],
            ]);
        }

        if (! config('services.gemini.key')) {
            return response()->json([
                'message' => 'El asistente de IA todavía no está configurado. Añade GEMINI_API_KEY al entorno del servidor.',
            ], 503);
        }

        try {
            $answer = $assistant->reply(
                $validated['mode'],
                $validated['messages'] ?? [],
                $prompt,
                $image,
            );
        } catch (ConnectionException $exception) {
            Log::warning('No fue posible conectar con el servicio del asistente de RESGUARDA.');

            return response()->json([
                'message' => 'El servicio de IA no está disponible en este momento. Inténtalo más tarde.',
            ], 503);
        } catch (RequestException $exception) {
            $providerResponse = $exception->response;
            $status = $providerResponse?->status();
            $sensitiveValues = [
                $prompt,
                ...array_column($validated['messages'] ?? [], 'content'),
            ];
            if ($image !== null) {
                $sensitiveValues[] = base64_encode($image->getContent());
            }

            Log::warning('Gemini rechazó o no pudo procesar una solicitud del asistente.', [
                'status' => $status,
                'provider_message' => $this->safeProviderMessage($providerResponse, $sensitiveValues),
            ]);

            if ($status === 400) {
                return response()->json([
                    'message' => 'Gemini no pudo procesar la consulta. Revisa el texto o la imagen e inténtalo de nuevo.',
                ], 422);
            }

            if (in_array($status, [401, 403], true)) {
                return response()->json([
                    'message' => 'La configuración o los permisos del servicio de IA no permiten completar la consulta. Contacta al administrador.',
                ], 503);
            }

            if ($status === 429) {
                return response()->json([
                    'message' => 'El servicio de IA alcanzó su límite de uso. Inténtalo más tarde.',
                ], 429);
            }

            if ($status === 404) {
                return response()->json([
                    'message' => 'El modelo Gemini configurado no está disponible. Contacta al administrador.',
                ], 503);
            }

            if (is_int($status) && $status >= 500) {
                return response()->json([
                    'message' => 'El servicio de IA está temporalmente indisponible. Inténtalo más tarde.',
                ], 503);
            }

            return response()->json([
                'message' => 'No se pudo obtener una respuesta del servicio de IA. Inténtalo más tarde.',
            ], 502);
        } catch (UnexpectedValueException $exception) {
            Log::warning('El servicio del asistente de RESGUARDA devolvió una respuesta no válida.');

            return response()->json([
                'message' => 'El servicio de IA devolvió una respuesta no válida. Inténtalo más tarde.',
            ], 502);
        }

        return response()->json([
            'answer' => $answer,
            'notice' => 'Orientación informativa generada por IA; no sustituye a profesionales ni certifica la seguridad.',
        ]);
    }

    private function safeProviderMessage(?Response $response, array $sensitiveValues = []): string
    {
        $message = $response?->json('error.message');
        if (! is_string($message) || trim($message) === '') {
            return 'El proveedor no incluyó un mensaje de error.';
        }

        foreach ($sensitiveValues as $sensitiveValue) {
            if (is_string($sensitiveValue) && $sensitiveValue !== '') {
                $message = str_replace($sensitiveValue, '[REDACTED_INPUT]', $message);
            }
        }

        $apiKey = config('services.gemini.key');
        if (is_string($apiKey) && $apiKey !== '') {
            $message = str_replace($apiKey, '[REDACTED]', $message);
        }

        $message = preg_replace('/AIza[0-9A-Za-z_-]{20,}/', '[REDACTED_KEY]', $message) ?? '';
        $message = preg_replace('/https?:\/\/\S+/i', '[URL_REDACTED]', $message) ?? '';
        $message = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $message) ?? '';
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        return mb_substr($message !== '' ? $message : 'Mensaje de error vacío tras saneamiento.', 0, 500);
    }
}
