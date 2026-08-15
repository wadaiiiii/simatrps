<?php

return [
    'provider' => env('SIMATRPS_AI_PROVIDER', 'openai'),
    'model' => env('OPENAI_MODEL', 'gpt-5-mini'),
    'api_key' => env('OPENAI_API_KEY'),
    'base_url' => rtrim(env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
    'timeout' => (int) env('OPENAI_TIMEOUT', 120),
    'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 12000),
];
