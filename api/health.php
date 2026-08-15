<?php

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$tmp = '/tmp';

http_response_code(200);

echo json_encode([
    'ok' => true,
    'php' => PHP_VERSION,
    'vercel' => (bool) getenv('VERCEL'),
    'vendor_autoload' => file_exists($root.'/vendor/autoload.php'),
    'bootstrap_app' => file_exists($root.'/bootstrap/app.php'),
    'vite_manifest' => file_exists($root.'/public/build/manifest.json'),
    'app_key_present' => (string) getenv('APP_KEY') !== '',
    'db_host_present' => (string) getenv('DB_HOST') !== '',
    'tmp_writable' => is_writable($tmp),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
