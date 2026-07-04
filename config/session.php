<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Session Driver
    |--------------------------------------------------------------------------
    | Supported: "file", "database", "redis"
    |
    | file     → PHP native file-based sessions (default)
    | database → Requires a `sessions` table (see DatabaseSessionHandler)
    | redis    → Requires the ext-redis extension
    |
    */
    'driver' => env('SESSION_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime (minutes)
    |--------------------------------------------------------------------------
    */
    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    /*
    |--------------------------------------------------------------------------
    | Session Table (database driver only)
    |--------------------------------------------------------------------------
    */
    'table' => env('SESSION_TABLE', 'sessions'),

    /*
    |--------------------------------------------------------------------------
    | Redis Connection (redis driver only)
    |--------------------------------------------------------------------------
    */
    'host'     => env('REDIS_HOST',     '127.0.0.1'),
    'port'     => (int) env('REDIS_PORT', 6379),
    'password' => env('REDIS_PASSWORD', null),
];
