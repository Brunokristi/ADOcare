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

    'ors' => [
        'key' => env('ORS_API_KEY'),
    ],

    'google' => [
        'maps_key' => env('GOOGLE_MAPS_API_KEY'),
        'geocoding_key' => env('GOOGLE_MAPS_GEOCODING_API_KEY'),
    ],

    'route_service' => [
        'base_url' => env('ROUTE_SERVICE_BASE_URL', 'http://127.0.0.1:1515'),
        'endpoint' => env('ROUTE_SERVICE_DISTANCE_ENDPOINT', '/tsp-solver'),
        'timeout' => env('ROUTE_SERVICE_TIMEOUT', 200),
    ],

    'udzs' => [
        'base_url' => env('UDZS_BASE_URL', 'https://api.udzs-sk.sk/api'),
        'email' => env('UDZS_EMAIL'),
        'password' => env('UDZS_PASSWORD'),
        'token_ttl' => env('UDZS_TOKEN_TTL', 3300),
        'timeout' => env('UDZS_TIMEOUT', 10),
    ],

    'ocr' => [
        'url' => env('OCR_SERVICE_URL', 'http://127.0.0.1:8081'),
    ],


];
