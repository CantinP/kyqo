<?php

/**
 * Kyqo Security Configuration
 *
 * Central place to tune all security-related settings.
 * Values here are consumed by the middleware stack at boot time.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    | List of IP addresses that are trusted reverse proxies.
    | Only these IPs are allowed to set X-Forwarded-For headers.
    | Use '*' only in development behind a known proxy.
    |
    */
    'trusted_proxies' => array_filter(explode(',', env('TRUSTED_PROXIES', ''))),

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    | URIs excluded from CSRF verification (e.g. webhooks, external API calls).
    |
    */
    'csrf' => [
        'except' => [
            'api/*',
            'webhooks/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    */
    'throttle' => [
        'global'   => ['max_attempts' => 120, 'decay_minutes' => 1],
        'api'      => ['max_attempts' => 60,  'decay_minutes' => 1],
        'login'    => ['max_attempts' => 5,   'decay_minutes' => 15],
        'register' => ['max_attempts' => 10,  'decay_minutes' => 60],
    ],

    /*
    |--------------------------------------------------------------------------
    | Body Size Limits
    |--------------------------------------------------------------------------
    |
    */
    'max_body_size'  => (int) env('MAX_BODY_SIZE', 10 * 1024 * 1024),   // 10 MB
    'max_upload_size'=> (int) env('MAX_UPLOAD_SIZE', 5 * 1024 * 1024),  // 5 MB

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    | The CSP is enforced via the SecurityHeaders middleware.
    | A per-request nonce is automatically generated and injected.
    |
    */
    'csp' => [
        'enabled'    => (bool) env('CSP_ENABLED', true),
        'report_uri' => env('CSP_REPORT_URI', ''),
        'directives' => [
            "default-src 'self'",
            "script-src 'self' 'nonce-{nonce}'",
            "style-src 'self' 'nonce-{nonce}'",
            "img-src 'self' data: https:",
            "font-src 'self'",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            "upgrade-insecure-requests",
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    |
    */
    'session' => [
        'cookie_secure'    => (bool) env('SESSION_SECURE_COOKIE', true),
        'cookie_http_only' => true,
        'cookie_same_site' => 'Strict',
        'lifetime'         => (int) env('SESSION_LIFETIME', 120),  // minutes
        'regenerate_on_auth' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    |
    */
    'hsts' => [
        'enabled'            => (bool) env('HSTS_ENABLED', true),
        'max_age'            => 31536000, // 1 year
        'include_subdomains' => true,
        'preload'            => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | APP_KEY Validation
    |--------------------------------------------------------------------------
    | Minimum required length for APP_KEY (in bytes after base64 decode).
    |
    */
    'app_key_min_length' => 32,

];
