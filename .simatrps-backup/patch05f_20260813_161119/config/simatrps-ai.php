<?php

return [
    'provider' => env('SIMATRPS_AI_PROVIDER', 'gemini'),
    'fallback_enabled' => filter_var(env('SIMATRPS_AI_FALLBACK', true), FILTER_VALIDATE_BOOL),

    'gemini' => [
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => rtrim(env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 120),
    ],

    'groq' => [
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        'api_key' => env('GROQ_API_KEY'),
        'base_url' => rtrim(env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'), '/'),
        'timeout' => (int) env('GROQ_TIMEOUT', 120),
    ],
];
