<?php

return [
    'ai' => [
        'provider'   => env('AI_PROVIDER', 'openai'),
        'api_key'    => env('AI_API_KEY', ''),
        'model'      => env('AI_MODEL', 'gpt-4o'),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 2000),
    ],

    'evolution' => [
        'base_url'  => env('EVOLUTION_BASE_URL', ''),
        'api_key'   => env('EVOLUTION_API_KEY', ''),
        'instance'  => env('EVOLUTION_INSTANCE', ''),
    ],

    'meta' => [
        'app_id'      => env('META_APP_ID', ''),
        'app_secret'  => env('META_APP_SECRET', ''),
        'api_version' => env('META_API_VERSION', 'v21.0'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
    ],
];
