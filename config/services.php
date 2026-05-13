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
        'geocoding_key' => env('GOOGLE_MAPS_API_KEY'),
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

    'gemini' => [
        'api_key' => env('GOOGLE_GEMINI_API_KEY'),
        'model' => env('GOOGLE_GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

    'vertex_ai' => [
        'project_id' => env('VERTEX_PROJECT_ID'),
        'location' => env('VERTEX_LOCATION', 'europe-west1'),
        'endpoint_id' => env('VERTEX_ENDPOINT_ID'),
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'general_location' => env('VERTEX_GENERAL_LOCATION', 'global'),
        'general_model' => env('VERTEX_GENERAL_MODEL', 'gemini-2.0-flash'),
        'general_models' => env('VERTEX_GENERAL_MODELS', ''),
        'auto_train' => [
            'enabled' => env('VERTEX_AUTOTRAIN_ENABLED', false),
            'schedule' => env('VERTEX_AUTOTRAIN_SCHEDULE', 'daily'),
            'weekday' => (int) env('VERTEX_AUTOTRAIN_WEEKDAY', 1),
            'time' => env('VERTEX_AUTOTRAIN_TIME', '02:30'),
            'min_new_feedback' => (int) env('VERTEX_AUTOTRAIN_MIN_NEW_FEEDBACK', 25),
            'source' => env('VERTEX_AUTOTRAIN_SOURCE', 'proposal_ai_prefill'),
            'dataset_disk' => env('VERTEX_AUTOTRAIN_DATASET_DISK', 'gcs'),
            'dataset_prefix' => env('VERTEX_AUTOTRAIN_DATASET_PREFIX', 'ai/dekurz-feedback'),
            'local_dataset_path' => env('VERTEX_AUTOTRAIN_LOCAL_DATASET_PATH', 'storage/app/private/ai-dataset-dekurz-feedback/train.jsonl'),
            'state_path' => env('VERTEX_AUTOTRAIN_STATE_PATH', 'ai/dekurz-autotrain/state.json'),
            'training_endpoint' => env('VERTEX_AUTOTRAIN_TRAINING_ENDPOINT', 'tuningJobs'),
            'base_model' => env('VERTEX_AUTOTRAIN_BASE_MODEL', 'gemini-2.0-flash-001'),
            'base_models' => env('VERTEX_AUTOTRAIN_BASE_MODELS', 'gemini-1.5-pro-002,gemini-1.5-flash-002'),
        ],
    ],


];
