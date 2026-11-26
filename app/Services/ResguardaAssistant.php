<?php

namespace App\Services;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

/**
 * Cliente de Gemini para el asistente. Para reducir la espera:
 * - las respuestas pueden transmitirse por fragmentos (streaming),
 * - si el modelo principal está saturado o no responde a tiempo se usa el
 *   siguiente modelo configurado en `services.gemini.fallback_models`,
 * - se pide un razonamiento mínimo y respuestas breves.
 */
class ResguardaAssistant
{
    /**
     * Estados del proveedor que justifican probar con otro modelo.
     */
    private const RETRYABLE_STATUSES = [404, 429, 500, 502, 503, 504];

    /**
     * Tiempo máximo que un modelo de respaldo puede tardar en empezar a responder.
     */
    private const FALLBACK_TIMEOUT_SECONDS = 15;

    /**
     * Presupuesto total para obtener respuesta entre todos los modelos; debe ser
     * menor que el límite de ejecución que fija el controlador.
     */
    private const TOTAL_BUDGET_SECONDS = 60;

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  string|null  $context  Datos verificados de RESGUARDA que el modelo puede citar.
     */
    public function reply(string $mode, array $history, string $prompt, ?UploadedFile $image, ?string $context = null): string
    {
        $payload = $this->payload($mode, $history, $prompt, $image, $context);

        $response = $this->withFallback(function (string $model, ?string $thinkingLevel, int $timeout) use ($payload): Response {
            return $this->request($timeout)
                ->post($this->endpoint($model, 'generateContent'), $this->withGenerationConfig($payload, $model, $thinkingLevel))
                ->throw();
        });

        $answer = trim(implode("\n", $this->textParts($response->json())));
        if ($answer === '') {
            throw new UnexpectedValueException('La API no devolvió una respuesta válida.');
        }

        return $answer;
    }

    /**
     * Devuelve la respuesta en fragmentos a medida que Gemini la genera.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return Generator<int, string>
     */
    public function stream(string $mode, array $history, string $prompt, ?UploadedFile $image, ?string $context = null): Generator
    {
        $payload = $this->payload($mode, $history, $prompt, $image, $context);

        $response = $this->withFallback(function (string $model, ?string $thinkingLevel, int $timeout) use ($payload): Response {
            return $this->request($timeout)
                ->accept('text/event-stream')
                ->withOptions(['stream' => true, 'read_timeout' => self::FALLBACK_TIMEOUT_SECONDS])
                ->post($this->endpoint($model, 'streamGenerateContent').'?alt=sse', $this->withGenerationConfig($payload, $model, $thinkingLevel))
                ->throw();
        });

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $emitted = false;

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newline));
                $buffer = substr($buffer, $newline + 1);

                foreach ($this->sseText($line) as $text) {
                    $emitted = true;

                    yield $text;
                }
            }
        }

        foreach ($this->sseText(trim($buffer)) as $text) {
            $emitted = true;

            yield $text;
        }

        if (! $emitted) {
            throw new UnexpectedValueException('La API no devolvió una respuesta válida.');
        }
    }

    /**
     * Prueba el modelo principal y, ante saturación o falta de respuesta, los
     * modelos de respaldo. Si un modelo rechaza el nivel de razonamiento se
     * reintenta una vez con el nivel por defecto.
     *
     * Mientras quedan modelos de respaldo, cada intento tiene poco tiempo para
     * empezar a responder; el último dispone del tiempo restante del presupuesto.
     *
     * @param  callable(string, string|null, int): Response  $send
     */
    private function withFallback(callable $send): Response
    {
        $lastException = null;
        $models = $this->models();
        $deadline = microtime(true) + self::TOTAL_BUDGET_SECONDS;

        foreach ($models as $index => $model) {
            $remaining = (int) floor($deadline - microtime(true));

            if ($remaining < 3) {
                break;
            }

            $thinkingLevel = $this->thinkingLevel($model);
            $timeout = $index < count($models) - 1 ? min(self::FALLBACK_TIMEOUT_SECONDS, $remaining) : $remaining;

            try {
                try {
                    return $send($model, $thinkingLevel, $timeout);
                } catch (RequestException $exception) {
                    if ($thinkingLevel !== null && $exception->response->status() === 400
                        && str_contains((string) $exception->response->json('error.message'), 'hinking')) {
                        return $send($model, 'LOW', max(3, min($timeout, (int) floor($deadline - microtime(true)))));
                    }

                    throw $exception;
                }
            } catch (ConnectionException $exception) {
                $lastException = $exception;
            } catch (RequestException $exception) {
                if (! in_array($exception->response->status(), self::RETRYABLE_STATUSES, true)) {
                    throw $exception;
                }

                $lastException = $exception;
            }
        }

        throw $lastException ?? new UnexpectedValueException('No hay modelos de IA configurados.');
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<string, mixed>
     */
    private function payload(string $mode, array $history, string $prompt, ?UploadedFile $image, ?string $context): array
    {
        $input = [];
        foreach ($history as $message) {
            $input[] = [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [
                    ['text' => $message['content']],
                ],
            ];
        }

        $parts = [];
        if ($prompt !== '') {
            $parts[] = ['text' => $prompt];
        } else {
            $parts[] = ['text' => 'Analiza la imagen según el modo seleccionado y ofrece orientación prudente.'];
        }

        if ($image !== null) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $image->getMimeType(),
                    'data' => base64_encode($image->getContent()),
                ],
            ];
        }

        $input[] = [
            'role' => 'user',
            'parts' => $parts,
        ];

        return [
            'system_instruction' => [
                'parts' => [
                    ['text' => $this->systemPrompt($mode, $context)],
                ],
            ],
            'contents' => $input,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withGenerationConfig(array $payload, string $model, ?string $thinkingLevel): array
    {
        $config = ['maxOutputTokens' => 1024];

        if ($thinkingLevel !== null && str_starts_with($model, 'gemini-3')) {
            $config['thinkingConfig'] = ['thinkingLevel' => $thinkingLevel];
        }

        return [...$payload, 'generationConfig' => $config];
    }

    /**
     * `thinkingLevel` solo existe en la familia Gemini 3; enviarlo a modelos
     * anteriores provoca un error 400.
     */
    private function thinkingLevel(string $model): ?string
    {
        if (! str_starts_with($model, 'gemini-3')) {
            return null;
        }

        $level = strtoupper(trim((string) config('services.gemini.thinking_level', 'MINIMAL')));

        return $level !== '' ? $level : 'LOW';
    }

    /**
     * @return list<string>
     */
    private function models(): array
    {
        $fallbacks = config('services.gemini.fallback_models', []);
        $fallbacks = is_string($fallbacks) ? explode(',', $fallbacks) : (array) $fallbacks;

        $models = array_map(
            fn (mixed $model): string => (string) preg_replace('#^(?:models/)+#', '', trim((string) $model)),
            [config('services.gemini.model'), ...$fallbacks],
        );

        return array_values(array_unique(array_filter($models, fn (string $model): bool => $model !== '')));
    }

    /**
     * @return list<string>
     */
    private function textParts(mixed $json): array
    {
        $textParts = [];
        $candidates = is_array($json) ? ($json['candidates'] ?? []) : [];

        foreach (is_array($candidates) ? $candidates : [] as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            foreach ($candidate['content']['parts'] ?? [] as $part) {
                if (is_array($part) && is_string($part['text'] ?? null) && ! ($part['thought'] ?? false)) {
                    $textParts[] = $part['text'];
                }
            }
        }

        return $textParts;
    }

    /**
     * @return list<string>
     */
    private function sseText(string $line): array
    {
        if (! str_starts_with($line, 'data:')) {
            return [];
        }

        $json = json_decode(trim(substr($line, 5)), true);

        return array_values(array_filter($this->textParts($json), fn (string $text): bool => $text !== ''));
    }

    private function request(int $timeout): PendingRequest
    {
        $request = Http::acceptJson()
            ->withHeaders([
                'x-goog-api-key' => (string) config('services.gemini.key'),
            ])
            ->connectTimeout(5)
            ->timeout($timeout);

        $caBundle = config('services.gemini.ca_bundle');
        if (is_string($caBundle) && $caBundle !== '' && is_file($caBundle)) {
            $request = $request->withOptions(['verify' => $caBundle]);
        }

        return $request;
    }

    private function endpoint(string $model, string $method): string
    {
        return sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:%s',
            rawurlencode($model),
            $method,
        );
    }

    private function systemPrompt(string $mode, ?string $context): string
    {
        $instructions = match ($mode) {
            'general' => 'Eres el asistente de prevención y respuesta ante emergencias de RESGUARDA para los 12 distritos de la provincia de Trujillo (La Libertad, Perú). Responde preguntas sobre prevención de riesgos (sismos, tsunamis, lluvias, huaicos, inundaciones, incendios), seguridad ciudadana, preparación de mochilas de emergencia, planes familiares, y sobre cómo usar RESGUARDA (mapa de riesgo, zonas seguras, reportes ciudadanos, evidencias, alertas, centro de comunicación, botón SOS y contactos de emergencia). Para preguntas sobre refugios, zonas seguras, puntos de encuentro, alertas o el nivel de riesgo de un distrito usa EXCLUSIVAMENTE los DATOS DE RESGUARDA incluidos abajo.',
            'first_aid' => 'Orienta sobre primeros auxilios básicos. Describe imágenes solo en términos observables; no diagnostiques, no infieras causas como certezas ni sustituyas a profesionales. Señala signos de alarma y recomienda contactar servicios de emergencia locales cuando pueda existir peligro inmediato. Da pasos simples, prudentes y de bajo riesgo.',
            'infrastructure' => 'Describe únicamente daños visibles en la imagen de paredes, columnas, techos o edificios. Recomienda mantener distancia, evitar entrar o tocar zonas dañadas y contactar autoridades o profesionales competentes si hay señales preocupantes. Nunca declares ni sugieras que una estructura es segura basándote en una imagen.',
            'objects' => 'Identifica el objeto solo si es posible, explicando la incertidumbre. Indica posibles usos útiles durante una emergencia sin afirmar capacidades que no sean visibles o conocidas. No hagas afirmaciones definitivas cuando la imagen no permita identificarlo.',
            default => 'Responde de forma prudente y segura.',
        };

        $prompt = 'Eres el asistente informativo de RESGUARDA. Responde siempre en español, con claridad y sin afirmar que una emergencia fue atendida. '
            .$instructions
            .' Si no puedes determinar algo, dilo claramente. No solicites datos personales innecesarios.'
            .' Reglas obligatorias: nunca inventes direcciones, refugios, zonas seguras, capacidades, rutas, teléfonos ni alertas oficiales;'
            .' si un dato no aparece en los DATOS DE RESGUARDA, indica que RESGUARDA no tiene esa información registrada y sugiere consultar a la municipalidad, INDECI o las autoridades competentes.'
            .' No afirmes que un lugar es seguro: describe el estado registrado y aclara que la situación puede cambiar.'
            .' Las distancias proporcionadas son en línea recta; sugiere usar la opción Cómo llegar del mapa para ver la ruta.'
            .' Ante una emergencia en curso o peligro para la vida, prioriza las indicaciones de las autoridades y recuerda las líneas de emergencia de Perú: 105 (Policía Nacional), 116 (Bomberos) y 106 (SAMU), además del botón SOS de RESGUARDA.'
            .' Usa el historial de la conversación para entender preguntas de seguimiento.'
            .' Responde como en un chat: frases cortas y directas, en menos de 80 palabras, salvo que el usuario pida más detalle.'
            .' Si das pasos, usa como máximo 4 puntos breves. No repitas advertencias ni la pregunta del usuario.'
            .' No utilices formato Markdown ni asteriscos; escribe en texto plano sin títulos.';

        if ($context !== null && trim($context) !== '') {
            $prompt .= PHP_EOL.PHP_EOL.$context;
        }

        return $prompt;
    }
}
