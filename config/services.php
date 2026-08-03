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

    'gemini' => [
        'key' => env('GEMINI_API_KEY', ''),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY', ''),
    ],

    'whatsapp' => [
        'token' => env('WHATSAPP_CLOUD_API_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v20.0'),
        'manager_template' => env('WHATSAPP_MANAGER_TEMPLATE_NAME'),
        'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'ar'),
    ],

    'firebase' => [
        // FIREBASE_CREDENTIALS_PATH is normally set relative to the project root
        // (e.g. "storage/app/firebase-service-account.json"). Resolve it against
        // base_path() so it still opens under a real web server, whose working
        // directory is the public/ docroot rather than the project root that
        // `php artisan` runs from. Absolute paths (unix or Windows drive-letter) pass through untouched.
        'credentials_path' => env('FIREBASE_CREDENTIALS_PATH') && ! preg_match('#^([A-Za-z]:[\\\\/]|/)#', env('FIREBASE_CREDENTIALS_PATH'))
            ? base_path(env('FIREBASE_CREDENTIALS_PATH'))
            : env('FIREBASE_CREDENTIALS_PATH'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),
    ],

];
