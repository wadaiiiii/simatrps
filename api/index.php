<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$root = dirname(__DIR__);

// Vercel Functions have a read-only project filesystem at runtime.
// Redirect every Laravel writable path to /tmp.
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

// Safe defaults for a serverless runtime. Vercel environment variables
// still take precedence whenever they are configured.
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

// Vercel terminates HTTPS before forwarding the request to the function.
if (getenv('VERCEL')) {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
}

if (file_exists($maintenance = $root.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $root.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once $root.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
