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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'facebook' => [
        'client_id' => env('FACEBOOK_APP_ID'),
        'client_secret' => env('FACEBOOK_APP_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT'),
    ],
    'google' => [
        'client_id' => env('google_CLIENT_ID'),
        'client_secret' => env('google_CLIENT_SECRET'),
        'redirect' => env('google_redirect'),
    ],
    'store_create_ai_bot' => [
        'base_url' => env('STORE_CREATE_AI_BOT_URL', 'http://127.0.0.1:8091'),
        'timeout' => (int) env('STORE_CREATE_AI_BOT_TIMEOUT', 240),
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 45),
    ],
    'domainnameapi' => [
        'environment' => env('DOMAINNAMEAPI_ENV', 'prod'),
        'reseller_id' => env('DOMAINNAMEAPI_RESELLER_ID'),
        'api_key' => env('DOMAINNAMEAPI_API_KEY'),
        'username' => env('DOMAINNAMEAPI_USERNAME'),
        'password' => env('DOMAINNAMEAPI_PASSWORD'),
        'check_url' => env('DOMAINNAMEAPI_CHECK_URL', 'https://api.domainnameapi.com/api/domains/available.json'),
        'check_method' => env('DOMAINNAMEAPI_CHECK_METHOD', 'POST'),
        'timeout' => (int) env('DOMAINNAMEAPI_TIMEOUT', 12),
        'supported_tlds' => env('DOMAINNAMEAPI_TLDS', 'com,net,org,info,store,shop,xyz,online'),
        'prices_bdt' => env('DOMAINNAMEAPI_PRICES_BDT', 'com:1700,net:1800,org:1800,info:1900,store:2200,shop:2200,xyz:1600,online:2200'),
        'default_nameservers' => array_filter(array_map('trim', explode(',', env('DOMAINNAMEAPI_NAMESERVERS', 'tr.apiname.com,eu.apiname.com')))),
    ],

];
