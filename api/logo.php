<?php

$path = dirname(__DIR__).'/public/logo-unsulbar.png';

if (! is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Logo not found';
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
header('Content-Length: '.filesize($path));

readfile($path);
