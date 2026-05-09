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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),
        'android_client_id' => env('GOOGLE_ANDROID_CLIENT_ID'),
        'ios_client_id' => env('GOOGLE_IOS_CLIENT_ID'),
        'web_client_id' => env('GOOGLE_WEB_CLIENT_ID'),
        'allowed_client_ids' => array_values(array_filter(array_map(
            static fn (string $value) => trim($value),
            explode(',', (string) env(
                'GOOGLE_ALLOWED_CLIENT_IDS',
                implode(',', array_filter([
                    env('GOOGLE_ANDROID_CLIENT_ID'),
                    env('GOOGLE_IOS_CLIENT_ID'),
                    env('GOOGLE_WEB_CLIENT_ID'),
                ]))
            ))
        ))),
        'jwks_url' => env('GOOGLE_ID_TOKEN_JWKS_URL', 'https://www.googleapis.com/oauth2/v3/certs'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'notifications' => [
        'webhook_url' => env('WEBHOOK_URL'),
        'webhook_token' => env('WEBHOOK_TOKEN'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URL'),
        'tenant_id' => env('MICROSOFT_TENANT_ID'),
    ],

];
