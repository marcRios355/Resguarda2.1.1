<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
        'sos_provider' => env('SOS_EMAIL_PROVIDER', 'log'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        // Ruta opcional a un bundle de certificados CA (cacert.pem) para entornos
        // como XAMPP en Windows donde PHP no tiene `curl.cainfo` configurado.
        'ca_bundle' => env('GEMINI_CA_BUNDLE'),
        // Modelos que se prueban, en orden, si el principal está saturado o no responde.
        'fallback_models' => env('GEMINI_FALLBACK_MODELS', 'gemini-3.5-flash-lite,gemini-flash-lite-latest'),
        // MINIMAL es el más rápido; los modelos que no lo admiten se reintentan con LOW.
        'thinking_level' => env('GEMINI_THINKING_LEVEL', 'MINIMAL'),
    ],

    'tomtom' => [
        'key' => env('TOMTOM_API_KEY'),
        // relative0 colorea cada calle según su velocidad actual frente a la libre.
        'style' => env('TOMTOM_TRAFFIC_STYLE', 'relative0'),
    ],

    'overpass' => [
        'url' => env('OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
    ],

];
