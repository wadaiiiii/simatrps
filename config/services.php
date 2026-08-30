<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have a
    | conventional file to locate this type of information.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sso' => [
        'issuer' => env('SSO_ISSUER_URL', env('APP_URL', 'https://simatrps.vercel.app')),
        'clients' => [
            'sipandu' => [
                // Exact-match allowlist. Keep the Vercel callback during the
                // migration window while the UNSULBAR subdirectory is enabled.
                'redirect_uris' => array_values(array_unique(array_filter([
                    env('SSO_SIPANDU_REDIRECT_URI', 'https://sipandumath.vercel.app/sso/callback'),
                    env('SSO_SIPANDU_REDIRECT_URI_CAMPUS', 'https://matematika.unsulbar.ac.id/akademik/sipandu/sso/callback'),
                ]))),
            ],
        ],
    ],

];
