<?php

return [
    'frontend_url' => env('WHATSAPP_AUTOMATION_FRONTEND_URL', 'http://localhost:5173'),
    'bot_api_url' => env('WHATSAPP_BOT_API_URL', ''),
    'bot_admin_token' => env('WHATSAPP_BOT_ADMIN_TOKEN', ''),
    /** Appended to bot_api_url for landing GET proxy (live client links). */
    'live_clients_path' => env('WHATSAPP_BOT_LIVE_CLIENTS_PATH', 'live-client-showcases'),
    'react_token_secret' => env('WHATSAPP_REACT_TOKEN_SECRET', ''),
    'react_token_ttl_minutes' => (int) env('WHATSAPP_REACT_TOKEN_TTL_MINUTES', 30),
    'react_code_ttl_minutes' => (int) env('WHATSAPP_REACT_CODE_TTL_MINUTES', 5),
    'react_cookie_name' => env('WHATSAPP_REACT_COOKIE_NAME', 'whatsapp_react_session'),
    'react_cookie_domain' => env('WHATSAPP_REACT_COOKIE_DOMAIN'),
    'react_cookie_secure' => filter_var(env('WHATSAPP_REACT_COOKIE_SECURE', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
    'react_cookie_same_site' => env('WHATSAPP_REACT_COOKIE_SAME_SITE', 'none'),
];
