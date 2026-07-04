<?php

namespace App\Http;

use Kyqo\Http\Kernel as BaseKernel;
use Kyqo\Http\Middleware\ConvertEmptyStringsToNull;
use Kyqo\Http\Middleware\TrimStrings;
use Kyqo\Http\Middleware\ValidateBodySize;
use Kyqo\Http\Middleware\SecurityHeaders;
use Kyqo\Http\Middleware\VerifyCsrfToken;
use Kyqo\Http\Middleware\ThrottleRequests;
use Kyqo\Http\Middleware\RedirectIfAuthenticated;
use Kyqo\Auth\Middleware\Authenticate;

/**
 * Application HTTP Kernel.
 *
 * FIX C2 – TrimStrings and ConvertEmptyStringsToNull were listed in BOTH
 *   $middleware (global) and $middlewareGroups['web'], causing double-execution
 *   on every web route.  Removed from $middleware; they now only run inside
 *   the 'web' group (applied via RouteServiceProvider).
 */
class Kernel extends BaseKernel
{
    /**
     * Global middleware — runs on EVERY request, web or API.
     * Keep this list small and stateless.
     */
    protected array $middleware = [
        ValidateBodySize::class,
        SecurityHeaders::class,
        ThrottleRequests::class,
    ];

    /**
     * Route middleware — applied per-route via ->middleware('alias').
     */
    protected array $routeMiddleware = [
        'auth'     => Authenticate::class,
        'guest'    => RedirectIfAuthenticated::class,
        'throttle' => ThrottleRequests::class,
        'csrf'     => VerifyCsrfToken::class,
        'headers'  => SecurityHeaders::class,
    ];

    /**
     * Middleware groups — applied automatically by RouteServiceProvider
     * for 'web' and 'api' route groups.
     */
    protected array $middlewareGroups = [
        'web' => [
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
            VerifyCsrfToken::class,
        ],
        'api' => [
            'throttle:60,1',
        ],
    ];
}
