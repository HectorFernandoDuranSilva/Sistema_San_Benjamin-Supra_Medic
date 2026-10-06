<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
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

    'bcb' => [
        'auto_update_on_web' => env('BCB_AUTO_UPDATE_ON_WEB', true),
        'auto_update_interval_hours' => env('BCB_AUTO_UPDATE_INTERVAL_HOURS', 4),
        'exchange_rate_url' => env('BCB_EXCHANGE_RATE_URL', 'https://www.bcb.gob.bo/librerias/indicadores/otras/ultimo.php'),
        'verify_ssl' => env('BCB_VERIFY_SSL', false),
        'timeout' => env('BCB_TIMEOUT', 15),
        'retries' => env('BCB_RETRIES', 2),
        'retry_sleep_ms' => env('BCB_RETRY_SLEEP_MS', 500),
        'fallback' => env('TIPO_CAMBIO_FALLBACK', 6.96),
    ],

];
