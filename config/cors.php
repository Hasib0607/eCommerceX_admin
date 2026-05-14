<?php

$parseCsv = static function ($value): array {
    if (!is_string($value) || trim($value) === '') {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn ($item) => trim($item),
        explode(',', $value)
    )));
};

$allowedOrigins = [];
$whatsAppFrontendUrl = trim((string) env('WHATSAPP_AUTOMATION_FRONTEND_URL', ''));

if ($whatsAppFrontendUrl !== '') {
    $allowedOrigins[] = rtrim($whatsAppFrontendUrl, '/');
}

$allowedOrigins = array_values(array_unique(array_merge(
    $allowedOrigins,
    $parseCsv(env('CORS_ALLOWED_ORIGINS', ''))
)));

$allowedOriginPatterns = $parseCsv(env('CORS_ALLOWED_ORIGIN_PATTERNS', ''));

if (empty($allowedOriginPatterns)) {
    // Shared API routes are used by WhatsApp admin, localhost frontends,
    // eBitans subdomains, and many client custom domains. Keep a broad
    // pattern fallback so credentials can still work without pinning the
    // whole API to a single origin.
    $allowedOriginPatterns = [
        '#^https?://localhost(:[0-9]+)?$#',
        '#^https?://127\\.0\\.0\\.1(:[0-9]+)?$#',
        '#^https?://([a-z0-9-]+\\.)*ecommercex\\.xyz$#i',
        '#^https?://([a-z0-9-]+\\.)*ebitans\\.com$#i',
        '#^https?://([a-z0-9-]+\\.)*ebitans\\.store$#i',
        '#^https?://([a-z0-9-]+\\.)*[a-z0-9-]+\\.[a-z]{2,}(:[0-9]+)?$#i',
    ];
}

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'react-admin-api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => $allowedOriginPatterns,

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
    

];
