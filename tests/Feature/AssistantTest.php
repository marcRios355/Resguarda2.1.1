<?php

use App\Models\User;
use App\Services\ResguardaAssistant;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->withoutVite();
    config([
        'services.gemini.key' => 'test-gemini-key-not-real',
        'services.gemini.model' => 'gemini-3.8-flash',
    ]);
});

test('guests cannot access the assistant page or endpoint', function () {
    $this->get(route('assistant.index'))->assertRedirect(route('login'));
    $this->postJson(route('assistant.chat'), [
        'mode' => 'first_aid',
        'prompt' => '¿Qué hago?',
    ])->assertUnauthorized();
});

test('authenticated users get a text answer through Gemini generateContent', function () {
    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'role' => 'model',
                        'parts' => [
                            ['text' => 'Busca atención profesional si empeora.'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), [
            'mode' => 'first_aid',
            'prompt' => '¿Qué cuidados básicos puedo tomar?',
            'messages' => [
                ['role' => 'user', 'content' => 'Tengo una pregunta.'],
                ['role' => 'assistant', 'content' => 'Claro, dime.'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('answer', 'Busca atención profesional si empeora.')
        ->assertJsonPath('notice', 'Orientación informativa generada por IA; no sustituye a profesionales ni certifica la seguridad.');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent'
        && $request->hasHeader('x-goog-api-key')
        && $request['system_instruction']['parts'][0]['text'] !== ''
        && $request['contents'][0]['role'] === 'user'
        && $request['contents'][0]['parts'][0]['text'] === 'Tengo una pregunta.'
        && $request['contents'][1]['role'] === 'model'
        && $request['contents'][1]['parts'][0]['text'] === 'Claro, dime.'
        && $request['contents'][2]['parts'][0]['text'] === '¿Qué cuidados básicos puedo tomar?'
        && $request['generationConfig']['maxOutputTokens'] === 900);
});

test('Gemini model prefix is normalized before building generateContent URL', function () {
    config(['services.gemini.model' => 'models/models/gemini-3.8-flash']);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'Respuesta de prueba.']]]],
            ],
        ]),
    ]);

    app(ResguardaAssistant::class)
        ->reply('objects', [], 'Pregunta de prueba', null);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent');
});

test('assistant validates prompt and selected mode', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), [
            'mode' => 'diagnosis',
            'prompt' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mode']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), [
            'mode' => 'objects',
            'prompt' => '   ',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['prompt']);
});

test('assistant reports missing Gemini configuration without making a request', function () {
    config(['services.gemini.key' => null]);
    Http::fake();

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), [
            'mode' => 'objects',
            'prompt' => '¿Qué es esto?',
        ])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'El asistente de IA todavía no está configurado. Añade GEMINI_API_KEY al entorno del servidor.');

    Http::assertNothingSent();
});

test('assistant endpoint is rate limited to five requests per minute per user', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'Orientación general.']]]],
            ],
        ]),
    ]);

    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($user)
            ->postJson(route('assistant.chat'), [
                'mode' => 'objects',
                'prompt' => "Consulta {$attempt}",
            ])
            ->assertOk();
    }

    $this->actingAs($user)
        ->postJson(route('assistant.chat'), [
            'mode' => 'objects',
            'prompt' => 'Consulta excedente',
        ])
        ->assertTooManyRequests();
});

test('Gemini quota errors return a safe message without provider details', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'error' => [
                'message' => 'sensitive quota or key details',
            ],
        ], 429),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), [
            'mode' => 'objects',
            'prompt' => '¿Qué es esto?',
        ])
        ->assertStatus(429)
        ->assertExactJson([
            'message' => 'El servicio de IA alcanzó su límite de uso. Inténtalo más tarde.',
        ])
        ->assertDontSee('sensitive quota or key details')
        ->assertDontSee('test-gemini-key-not-real');
});

test('other Gemini HTTP errors are returned without exposing provider details', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'error' => [
                'message' => 'sensitive provider response',
            ],
        ], 401),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), [
            'mode' => 'objects',
            'prompt' => '¿Qué es esto?',
        ])
        ->assertServiceUnavailable()
        ->assertExactJson([
            'message' => 'La configuración o los permisos del servicio de IA no permiten completar la consulta. Contacta al administrador.',
        ])
        ->assertDontSee('sensitive provider response');
});

test('an unavailable Gemini model returns a safe configuration message', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'error' => [
                'message' => 'model is not available to this account',
            ],
        ], 404),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), [
            'mode' => 'objects',
            'prompt' => '¿Qué es esto?',
        ])
        ->assertServiceUnavailable()
        ->assertExactJson([
            'message' => 'El modelo Gemini configurado no está disponible. Contacta al administrador.',
        ])
        ->assertDontSee('model is not available to this account');
});

test('temporary Gemini saturation returns a safe retry message', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'error' => [
                'message' => 'provider internal details',
            ],
        ], 503),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), [
            'mode' => 'objects',
            'prompt' => '¿Qué es esto?',
        ])
        ->assertServiceUnavailable()
        ->assertExactJson([
            'message' => 'El servicio de IA está temporalmente indisponible. Inténtalo más tarde.',
        ])
        ->assertDontSee('provider internal details');
});

test('Gemini request errors are logged with status and sanitized message only', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'error' => [
                'message' => 'Temporary issue with key test-gemini-key-not-real. See https://provider.invalid/debug',
            ],
        ], 503),
    ]);

    Log::spy();

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), [
            'mode' => 'objects',
            'prompt' => '¿Qué es esto?',
        ])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'El servicio de IA está temporalmente indisponible. Inténtalo más tarde.')
        ->assertDontSee('test-gemini-key-not-real')
        ->assertDontSee('provider.invalid');

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Gemini rechazó o no pudo procesar una solicitud del asistente.', [
            'status' => 503,
            'provider_message' => 'Temporary issue with key [REDACTED]. See [URL_REDACTED]',
        ]);
});

test('Gemini bad requests return a validation-style message without provider details', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'error' => [
                'message' => 'invalid image payload with internal detail',
            ],
        ], 400),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), [
            'mode' => 'objects',
            'prompt' => 'Describe esta imagen',
        ])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Gemini no pudo procesar la consulta. Revisa el texto o la imagen e inténtalo de nuevo.',
        ])
        ->assertDontSee('internal detail');
});

test('Gemini permission errors return an authentication configuration message', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'error' => ['message' => 'permission denied: sensitive provider detail'],
        ], 403),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), [
            'mode' => 'objects',
            'prompt' => '¿Qué es esto?',
        ])
        ->assertServiceUnavailable()
        ->assertExactJson([
            'message' => 'La configuración o los permisos del servicio de IA no permiten completar la consulta. Contacta al administrador.',
        ])
        ->assertDontSee('sensitive provider detail');
});

test('Gemini server errors are reported as temporary provider unavailability', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'error' => ['message' => 'internal provider failure'],
        ], 500),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), [
            'mode' => 'objects',
            'prompt' => '¿Qué es esto?',
        ])
        ->assertServiceUnavailable()
        ->assertExactJson([
            'message' => 'El servicio de IA está temporalmente indisponible. Inténtalo más tarde.',
        ])
        ->assertDontSee('internal provider failure');
});
