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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id'     => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect'      => env('FACEBOOK_REDIRECT_URI'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'verify_sid' => env('TWILIO_VERIFY_SID'),
    ],

    'gemini' => [
        'key' => env('GEMINI_APP_KEY'),
        'endpoint' => env('GEMINI_ENDPOINT'),
        'model' => env('GEMINI_MODEL'),
        'base'    => env('GEMINI_BASE', 'https://generativelanguage.googleapis.com'),
        'version' => env('GEMINI_VERSION', 'v1beta'),
        'timeout' => env('GEMINI_TIMEOUT', 20),
        'base' => env('GEMINI_BASE', 'https://generativeai.googleapis.com'),
        'version' => env('GEMINI_VERSION', 'v1beta'),
        'timeout' => env('GEMINI_TIMEOUT', 20)
    ],

    'payos' => [
        'client_id'    => env('PAYOS_CLIENT_ID'),
        'api_key'      => env('PAYOS_API_KEY'),
        'checksum_key' => env('PAYOS_CHECKSUM_KEY'),
        'return_url'   => env('PAYOS_RETURN_URL'),
        'cancel_url'   => env('PAYOS_CANCEL_URL'),
    ]
];
