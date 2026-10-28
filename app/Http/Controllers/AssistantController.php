<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Services\AssistantContextBuilder;
use App\Services\ResguardaAssistant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnexpectedValueException;

class AssistantController extends Controller
{
    private const NOTICE = 'Orientación informativa generada por IA; no sustituye a profesionales ni certifica la seguridad.';

    public function index(): InertiaResponse
    {
        return Inertia::render('assistant/Index', [
            'districts' => District::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function reply(Request $request, ResguardaAssistant $assistant, AssistantContextBuilder $contextBuilder): JsonResponse
    {
        $query = $this->prepare($request, $contextBuilder);

        if ($query instanceof JsonResponse) {
            return $query;
        }

        try {
            $answer = $assistant->reply($query['mode'], $query['history'], $query['prompt'], $query['image'], $query['context']['context']);
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            return $this->failureResponse($exception, $query);
        }

        return response()->json([
            'answer' => $answer,
            'notice' => self::NOTICE,
            ...$this->contextSummary($query),
        ]);
    }

    /**
     * Transmite la respuesta como Server-Sent Events para que el usuario vea el
     * texto mientras se genera. El primer fragmento se obtiene antes de abrir el
     * flujo, así los errores del proveedor siguen respondiendo con su código HTTP.
     */
    public function stream(Request $request, ResguardaAssistant $assistant, AssistantContextBuilder $contextBuilder): JsonResponse|StreamedResponse
    {
        $query = $this->prepare($request, $contextBuilder);

        if ($query instanceof JsonResponse) {
            return $query;
        }

        $chunks = $assistant->stream($query['mode'], $query['history'], $query['prompt'], $query['image'], $query['context']['context']);

        try {
            $first = $chunks->current();
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            return $this->failureResponse($exception, $query);
        }

        $summary = $this->contextSummary($query);

        return response()->stream(function () use ($chunks, $first, $summary): void {
            $send = function (array $event): void {
                echo 'data: '.json_encode($event, JSON_UNESCAPED_UNICODE)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            $send(['type' => 'delta', 'text' => $first]);

            try {
                $chunks->next();

                while ($chunks->valid()) {
                    $send(['type' => 'delta', 'text' => $chunks->current()]);
                    $chunks->next();
                }

                $send(['type' => 'done', 'notice' => self::NOTICE, ...$summary]);
            } catch (Throwable $exception) {
                Log::warning('La respuesta en streaming del asistente se interrumpió.', ['exception' => $exception::class]);
                $send(['type' => 'error', 'message' => 'La respuesta se interrumpió. Inténtalo de nuevo.']);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Valida la consulta y reúne el contexto de RESGUARDA.
     *
     * @return array{mode: string, history: array<int, array{role: string, content: string}>, prompt: string, image: UploadedFile|null, context: array{context: string, district: District|null, location_used: bool}}|JsonResponse
     */
    private function prepare(Request $request, AssistantContextBuilder $contextBuilder): array|JsonResponse
    {
        // Gemini puede tardar varios segundos y probar modelos de respaldo; el
        // límite por defecto de PHP (30 s) cortaría la respuesta a la mitad.
        if (function_exists('set_time_limit')) {
            set_time_limit(120);
        }

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['general', 'first_aid', 'infrastructure', 'objects'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
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

        return [
            'mode' => $validated['mode'],
            'history' => $validated['messages'] ?? [],
            'prompt' => $prompt,
            'image' => $image,
            'context' => $contextBuilder->build(
                isset($validated['latitude']) ? (float) $validated['latitude'] : null,
                isset($validated['longitude']) ? (float) $validated['longitude'] : null,
                isset($validated['district_id']) ? (int) $validated['district_id'] : null,
            ),
        ];
    }

    /**
     * @param  array{context: array{district: District|null, location_used: bool}}  $query
     * @return array{district: array{id: int, name: string}|null, location_used: bool}
     */
    private function contextSummary(array $query): array
    {
        $district = $query['context']['district'];

        return [
            'district' => $district !== null ? ['id' => $district->id, 'name' => $district->name] : null,
            'location_used' => $query['context']['location_used'],
        ];
    }

    /**
     * Convierte un fallo del proveedor en un mensaje comprensible sin exponer
     * detalles internos ni la clave.
     *
     * @param  array{prompt: string, history: array<int, array{role: string, content: string}>, image: UploadedFile|null}  $query
     */
    private function failureResponse(Throwable $exception, array $query): JsonResponse
    {
        if ($exception instanceof ConnectionException) {
            Log::warning('No fue posible conectar con el servicio del asistente de RESGUARDA.', [
                'reason' => $this->connectionFailureReason($exception),
            ]);

            return response()->json([
                'message' => 'El servicio de IA no está disponible en este momento. Inténtalo más tarde.',
            ], 503);
        }

        if ($exception instanceof UnexpectedValueException) {
            Log::warning('El servicio del asistente de RESGUARDA devolvió una respuesta no válida.');

            return response()->json([
                'message' => 'El servicio de IA devolvió una respuesta no válida. Inténtalo más tarde.',
            ], 502);
        }

        /** @var RequestException $exception */
        $providerResponse = $exception->response;
        $status = $providerResponse->status();
        $sensitiveValues = [
            $query['prompt'],
            ...array_column($query['history'], 'content'),
        ];
        if ($query['image'] !== null) {
            $sensitiveValues[] = base64_encode($query['image']->getContent());
        }

        Log::warning('Gemini rechazó o no pudo procesar una solicitud del asistente.', [
            'status' => $status,
            'provider_message' => $this->safeProviderMessage($providerResponse, $sensitiveValues),
        ]);

        return match (true) {
            $status === 400 => response()->json([
                'message' => 'Gemini no pudo procesar la consulta. Revisa el texto o la imagen e inténtalo de nuevo.',
            ], 422),
            in_array($status, [401, 403], true) => response()->json([
                'message' => 'La configuración o los permisos del servicio de IA no permiten completar la consulta. Contacta al administrador.',
            ], 503),
            $status === 429 => response()->json([
                'message' => 'El servicio de IA alcanzó su límite de uso. Inténtalo más tarde.',
            ], 429),
            $status === 404 => response()->json([
                'message' => 'El modelo Gemini configurado no está disponible. Contacta al administrador.',
            ], 503),
            $status >= 500 => response()->json([
                'message' => 'El servicio de IA está temporalmente indisponible. Inténtalo más tarde.',
            ], 503),
            default => response()->json([
                'message' => 'No se pudo obtener una respuesta del servicio de IA. Inténtalo más tarde.',
            ], 502),
        };
    }

    /**
     * Clasifica el fallo de conexión sin registrar URLs ni claves.
     */
    private function connectionFailureReason(ConnectionException $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'cURL error 60'), str_contains($message, 'SSL certificate') => 'ssl_certificate',
            str_contains($message, 'cURL error 28'), str_contains($message, 'timed out') => 'timeout',
            str_contains($message, 'cURL error 6'), str_contains($message, 'resolve host') => 'dns',
            default => 'network',
        };
    }

    /**
     * @param  array<int, string>  $sensitiveValues
     */
    private function safeProviderMessage(?Response $response, array $sensitiveValues = []): string
    {
        $message = $response?->json('error.message');
        if (! is_string($message) || trim($message) === '') {
            return 'El proveedor no incluyó un mensaje de error.';
        }

        foreach ($sensitiveValues as $sensitiveValue) {
            if ($sensitiveValue !== '') {
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
