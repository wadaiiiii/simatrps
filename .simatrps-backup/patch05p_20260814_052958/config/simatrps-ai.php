<?php

return [
    'provider' => env('SIMATRPS_AI_PROVIDER', 'groq'),

    // Only providers listed here are called automatically.
    // Gemini is intentionally excluded while the current project is denied access.
    'provider_chain' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('SIMATRPS_AI_PROVIDER_CHAIN', 'groq,mistral,cohere'))
    ))),

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
        'base_url' => rtrim(env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'), '/'),
        'timeout' => (int) env('GROQ_TIMEOUT', 180),
    ],

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
        'model' => env('MISTRAL_MODEL', 'mistral-small-latest'),
        'base_url' => rtrim(env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1'), '/'),
        'timeout' => (int) env('MISTRAL_TIMEOUT', 180),
    ],

    'cohere' => [
        'api_key' => env('COHERE_API_KEY'),
        'model' => env('COHERE_MODEL', 'command-a-plus-05-2026'),
        'base_url' => rtrim(env('COHERE_BASE_URL', 'https://api.cohere.ai/compatibility/v1'), '/'),
        'timeout' => (int) env('COHERE_TIMEOUT', 180),
    ],

    // Kept only so the key/model is not lost. It is not in provider_chain by default.
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        'base_url' => rtrim(env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 120),
    ],
];
