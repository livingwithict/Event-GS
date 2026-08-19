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

    /*
     * Sociair SMS. The config key was historically "sparrow" — the old env names
     * still work so a deployed .env keeps running, but new setups use SOCIAIR_*.
     * driver: "sociair" sends for real, anything else writes to the log.
     */
    'thirdfactor' => [
        'base_url' => env('THIRDFACTOR_BASE_URL', 'https://dnc-console.v3.thirdfactor.ai'),
        'api_key' => env('THIRDFACTOR_API_KEY'),
        'workflow_id' => env('THIRDFACTOR_WORKFLOW_ID'),
        'webhook_secret' => env('THIRDFACTOR_WEBHOOK_SECRET'),
        'callback_url' => env('THIRDFACTOR_CALLBACK_URL'),
        'expires_in_hours' => env('THIRDFACTOR_EXPIRES_IN_HOURS', 24),
    ],

    'pickndrop' => [
        'base_url' => env('PICKNDROP_BASE_URL', 'https://app-t.pickndropnepal.com'),
        'api_key' => env('PICKNDROP_API_KEY'),
        'api_secret' => env('PICKNDROP_API_SECRET'),
    ],

    'sparrow' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'token' => env('SOCIAIR_SMS_TOKEN', env('SPARROW_SMS_TOKEN')),
        'base_url' => env('SOCIAIR_SMS_BASE_URL', env('SPARROW_SMS_BASE_URL', 'https://sms.sociair.com/api')),
        'batch_size' => (int) env('SMS_BATCH_SIZE', 50),
    ],

];
