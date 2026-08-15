<?php

return [
    'provider' => env('SIMATRPS_AI_PROVIDER', 'groq'),
    'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
    'api_key' => env('GROQ_API_KEY'),
    'base_url' => rtrim(env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'), '/'),
    'timeout' => (int) env('GROQ_TIMEOUT', 120),
    'max_output_tokens' => (int) env('GROQ_MAX_OUTPUT_TOKENS', 3200),
];
