<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$root = dirname(__DIR__);

// Vercel Functions have a read-only project filesystem at runtime.
// Laravel may need writable temporary directories for compiled views.
$tmpStorage = '/tmp/simatrps-storage';

foreach ([
    $tmpStorage,
    $tmpStorage.'/framework',
    $tmpStorage.'/framework/cache',
    $tmpStorage.'/framework/cache/data',
    $tmpStorage.'/framework/sessions',
    $tmpStorage.'/framework/views',
    $tmpStorage.'/logs',
] as $directory) {
    if (! is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }
}

putenv('VIEW_COMPILED_PATH='.$tmpStorage.'/framework/views');
$_ENV['VIEW_COMPILED_PATH'] = $tmpStorage.'/framework/views';
$_SERVER['VIEW_COMPILED_PATH'] = $tmpStorage.'/framework/views';

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
