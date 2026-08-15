<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$tmpStorage = '/tmp/simatrps-storage';
$tmpBootstrapCache = '/tmp/simatrps-bootstrap-cache';

foreach ([
    $tmpStorage,
    $tmpStorage.'/framework',
    $tmpStorage.'/framework/cache',
    $tmpStorage.'/framework/cache/data',
    $tmpStorage.'/framework/sessions',
    $tmpStorage.'/framework/views',
    $tmpStorage.'/logs',
    $tmpBootstrapCache,
] as $directory) {
    if (! is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }
}

$runtimeEnvironment = [
    'LARAVEL_STORAGE_PATH' => $tmpStorage,
    'VIEW_COMPILED_PATH' => $tmpStorage.'/framework/views',
    'APP_CONFIG_CACHE' => $tmpBootstrapCache.'/config.php',
    'APP_EVENTS_CACHE' => $tmpBootstrapCache.'/events.php',
    'APP_PACKAGES_CACHE' => $tmpBootstrapCache.'/packages.php',
    'APP_ROUTES_CACHE' => $tmpBootstrapCache.'/routes.php',
    'APP_SERVICES_CACHE' => $tmpBootstrapCache.'/services.php',
];

foreach ($runtimeEnvironment as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$defaults = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'LOG_CHANNEL' => 'stderr',
    'SESSION_DRIVER' => 'cookie',
    'QUEUE_CONNECTION' => 'sync',
];

foreach ($defaults as $key => $value) {
    if (getenv($key) === false || getenv($key) === '') {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

if (getenv('VERCEL')) {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
}

$sanitize = static function (string $message): string {
    foreach ([
        'APP_KEY', 'DB_PASSWORD', 'GROQ_API_KEY', 'MISTRAL_API_KEY',
        'SAMBANOVA_API_KEY', 'OPENROUTER_API_KEY', 'HF_TOKEN',
        'COHERE_API_KEY', 'GEMINI_API_KEY',
    ] as $key) {
        $value = (string) getenv($key);
        if ($value !== '' && strlen($value) >= 4) {
            $message = str_replace($value, '[REDACTED]', $message);
        }
    }

    return $message;
};

$result = [
    'ok' => true,
    'checks' => [],
];

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';

    /** @var Kernel $kernel */
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    $result['checks']['laravel_boot'] = [
        'ok' => true,
        'version' => $app->version(),
        'env' => $app->environment(),
    ];

    $result['checks']['files'] = [
        'routes_web' => file_exists($root.'/routes/web.php'),
        'root_view' => file_exists($root.'/resources/views/app.blade.php'),
        'welcome_page' => file_exists($root.'/resources/js/pages/welcome.tsx'),
        'vite_manifest' => file_exists($root.'/public/build/manifest.json'),
        'bootstrap_cache_writable' => is_writable($tmpBootstrapCache),
    ];

    try {
        $connection = $app->make('db')->connection();
        $connection->select('select 1');
        $result['checks']['database'] = [
            'ok' => true,
            'driver' => $connection->getDriverName(),
        ];
    } catch (Throwable $e) {
        $result['ok'] = false;
        $result['checks']['database'] = [
            'ok' => false,
            'exception' => get_class($e),
            'message' => $sanitize($e->getMessage()),
        ];
    }

    try {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'text/html');
        $response = \Inertia\Inertia::render('welcome')->toResponse($request);

        $result['checks']['inertia_render'] = [
            'ok' => true,
            'status' => $response->getStatusCode(),
            'content_length' => strlen((string) $response->getContent()),
        ];
    } catch (Throwable $e) {
        $result['ok'] = false;
        $result['checks']['inertia_render'] = [
            'ok' => false,
            'exception' => get_class($e),
            'message' => $sanitize($e->getMessage()),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ];
    }
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['checks']['laravel_boot'] = [
        'ok' => false,
        'exception' => get_class($e),
        'message' => $sanitize($e->getMessage()),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ];
}

http_response_code(200);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
