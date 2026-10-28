<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class ResguardaAssistant
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function reply(string $mode, array $history, string $prompt, ?UploadedFile $image): string
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

        $response = $this->request()
            ->post($this->endpoint(), [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $this->systemPrompt($mode)],
                    ],
                ],
                'contents' => $input,
                'generationConfig' => [
                    'maxOutputTokens' => 2048,
                    'thinkingConfig' => [
                        'thinkingLevel' => 'LOW',
                    ],
                ],
            ])
            ->throw();

        $textParts = [];
        $candidates = $response->json('candidates', []);
        foreach (is_array($candidates) ? $candidates : [] as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            foreach ($candidate['content']['parts'] ?? [] as $part) {
                if (is_array($part) && is_string($part['text'] ?? null)) {
                    $textParts[] = $part['text'];
                }
            }
        }

        $answer = implode("\n", $textParts);
        if (! is_string($answer) || trim($answer) === '') {
            throw new UnexpectedValueException('La API no devolvió una respuesta válida.');
        }

        return trim($answer);
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders([
                'x-goog-api-key' => (string) config('services.gemini.key'),
            ])
            ->connectTimeout(5)
            ->timeout(20);
    }

    private function endpoint(): string
    {
        $model = preg_replace(
            '#^(?:models/)+#',
            '',
            trim((string) config('services.gemini.model')),
        );

        return sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            rawurlencode((string) $model),
        );
    }

    private function systemPrompt(string $mode): string
    {
        $instructions = match ($mode) {
            'first_aid' => 'Orienta sobre primeros auxilios básicos. Describe imágenes solo en términos observables; no diagnostiques, no infieras causas como certezas ni sustituyas a profesionales. Señala signos de alarma y recomienda contactar servicios de emergencia locales cuando pueda existir peligro inmediato. Da pasos simples, prudentes y de bajo riesgo.',
            'infrastructure' => 'Describe únicamente daños visibles en la imagen de paredes, columnas, techos o edificios. Recomienda mantener distancia, evitar entrar o tocar zonas dañadas y contactar autoridades o profesionales competentes si hay señales preocupantes. Nunca declares ni sugieras que una estructura es segura basándote en una imagen.',
            'objects' => 'Identifica el objeto solo si es posible, explicando la incertidumbre. Indica posibles usos útiles durante una emergencia sin afirmar capacidades que no sean visibles o conocidas. No hagas afirmaciones definitivas cuando la imagen no permita identificarlo.',
            default => 'Responde de forma prudente y segura.',
        };

        return 'Eres el asistente informativo de RESGUARDA. Responde siempre en español, con claridad y sin afirmar que una emergencia fue atendida. '
    .$instructions
    .' Si no puedes determinar algo, dilo claramente. No solicites datos personales innecesarios.'
    .' No utilices formato Markdown. No escribas asteriscos para crear negritas o cursivas. '
    .' Presenta las respuestas en texto plano, con listas numeradas y párrafos claros. '
    .' Puedes usar títulos sencillos sin símbolos decorativos.';
    }
}
