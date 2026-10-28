<?php

use App\Models\Alert;
use App\Models\District;
use App\Models\SafeZone;
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
        && $request['generationConfig']['maxOutputTokens'] === 1024
        && $request['generationConfig']['thinkingConfig']['thinkingLevel'] === 'MINIMAL');
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

test('assistant endpoint is rate limited to fifteen requests per minute per user', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'Orientación general.']]]],
            ],
        ]),
    ]);

    $user = User::factory()->create();

    foreach (range(1, 15) as $attempt) {
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

test('the assistant page lists districts for manual selection', function () {
    District::factory()->create(['name' => 'Huanchaco', 'geojson_bounds' => null]);

    $this->actingAs(User::factory()->create())
        ->get(route('assistant.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('assistant/Index')
            ->has('districts', 1)
            ->where('districts.0.name', 'Huanchaco')
        );
});

test('general questions include registered safe zones, alerts and the detected district without raw coordinates', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'La zona registrada más cercana es Coliseo Gran Chimú.']]]]],
        ]),
    ]);

    $trujillo = District::factory()->create([
        'name' => 'Trujillo',
        'latitude' => -8.1116,
        'longitude' => -79.0287,
        'geojson_bounds' => null,
    ]);
    District::factory()->create([
        'name' => 'Huanchaco',
        'latitude' => -8.0825,
        'longitude' => -79.1172,
        'geojson_bounds' => null,
    ]);
    SafeZone::factory()->create([
        'district_id' => $trujillo->id,
        'name' => 'Coliseo Gran Chimú',
        'latitude' => -8.1120,
        'longitude' => -79.0300,
        'is_verified' => true,
    ]);
    Alert::factory()->create([
        'district_id' => $trujillo->id,
        'title' => 'Lluvias intensas en el centro',
        'is_active' => true,
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), [
            'mode' => 'general',
            'prompt' => '¿Cuál es la zona segura más cercana?',
            'latitude' => -8.1118,
            'longitude' => -79.0290,
        ])
        ->assertOk()
        ->assertJsonPath('district.name', 'Trujillo')
        ->assertJsonPath('location_used', true);

    Http::assertSent(function (Request $request): bool {
        $system = $request['system_instruction']['parts'][0]['text'];

        return str_contains($system, 'Coliseo Gran Chimú')
            && str_contains($system, 'Lluvias intensas en el centro')
            && str_contains($system, 'distrito aproximado Trujillo')
            && str_contains($system, 'nunca inventes')
            && ! str_contains($system, '-8.1118')
            && ! str_contains($system, '-79.029');
    });
});

test('the assistant states when no safe zones are registered for the chosen district', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'No hay datos registrados.']]]]],
        ]),
    ]);

    $district = District::factory()->create(['name' => 'Simbal', 'geojson_bounds' => null]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), [
            'mode' => 'general',
            'prompt' => '¿Dónde hay un refugio?',
            'district_id' => $district->id,
        ])
        ->assertOk()
        ->assertJsonPath('location_used', false);

    Http::assertSent(fn (Request $request): bool => str_contains(
        $request['system_instruction']['parts'][0]['text'],
        'No hay zonas seguras registradas en RESGUARDA para el distrito Simbal.',
    ));
});

test('thinking configuration is only sent to Gemini 3 models', function () {
    config(['services.gemini.model' => 'gemini-2.5-flash']);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Respuesta.']]]]],
        ]),
    ]);

    app(ResguardaAssistant::class)->reply('general', [], 'Hola', null);

    Http::assertSent(fn (Request $request): bool => ! isset($request['generationConfig']['thinkingConfig'])
        && $request['generationConfig']['maxOutputTokens'] === 1024);
});

test('assistant rejects incomplete coordinates', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), [
            'mode' => 'general',
            'prompt' => '¿Dónde estoy?',
            'latitude' => -8.11,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['longitude']);
});

test('a saturated primary model falls back to the next configured model', function () {
    config(['services.gemini.fallback_models' => 'gemini-3.5-flash-lite']);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:*' => Http::response(['error' => ['message' => 'high demand']], 503),
        'generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Respuesta del modelo de respaldo.']]]]],
        ]),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.chat'), ['mode' => 'general', 'prompt' => 'Hola'])
        ->assertOk()
        ->assertJsonPath('answer', 'Respuesta del modelo de respaldo.');

    Http::assertSentCount(2);
});

test('models that reject the minimal thinking level are retried with LOW', function () {
    config(['services.gemini.fallback_models' => '']);

    Http::fakeSequence('generativelanguage.googleapis.com/*')
        ->push(['error' => ['message' => 'Thinking level MINIMAL is not supported for this model.']], 400)
        ->push(['candidates' => [['content' => ['parts' => [['text' => 'Listo.']]]]]]);

    expect(app(ResguardaAssistant::class)->reply('general', [], 'Hola', null))->toBe('Listo.');

    Http::assertSent(fn (Request $request): bool => ($request['generationConfig']['thinkingConfig']['thinkingLevel'] ?? null) === 'LOW');
});

test('the streaming endpoint sends the answer in fragments as server-sent events', function () {
    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:streamGenerateContent*' => Http::response(
            'data: '.json_encode(['candidates' => [['content' => ['parts' => [['text' => 'Mantén la calma. ']]]]]])."\r\n\r\n"
            .'data: '.json_encode(['candidates' => [['content' => ['parts' => [['text' => 'Ubícate en una zona segura.']]]]]])."\r\n\r\n",
            200,
            ['Content-Type' => 'text/event-stream'],
        ),
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->post(route('assistant.stream'), ['mode' => 'general', 'prompt' => '¿Qué hago en un sismo?'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

    $body = $response->streamedContent();
    expect($body)->toContain('"type":"delta","text":"Mantén la calma. "')
        ->toContain('"type":"delta","text":"Ubícate en una zona segura."')
        ->toContain('"type":"done"');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), ':streamGenerateContent?alt=sse'));
});

test('streaming errors before the first fragment return a normal JSON error', function () {
    config(['services.gemini.fallback_models' => '']);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota']], 429),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.stream'), ['mode' => 'general', 'prompt' => 'Hola'])
        ->assertStatus(429)
        ->assertExactJson(['message' => 'El servicio de IA alcanzó su límite de uso. Inténtalo más tarde.']);
});

test('when every model is unavailable the assistant answers with a clear error instead of crashing', function () {
    config(['services.gemini.fallback_models' => 'gemini-3.5-flash-lite']);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::failedConnection(),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.stream'), ['mode' => 'general', 'prompt' => 'Hola'])
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => 'El servicio de IA no está disponible en este momento. Inténtalo más tarde.']);

    Http::assertSentCount(2);
});
