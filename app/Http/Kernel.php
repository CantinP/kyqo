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
 * Global middleware runs on every request.
 * Route middleware is applied per-route via ->middleware('alias').
 */
class Kernel extends BaseKernel
{
    protected array $middleware = [
        ValidateBodySize::class,
        SecurityHeaders::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        VerifyCsrfToken::class,
        ThrottleRequests::class,
    ];

    protected array $routeMiddleware = [
        'auth'     => Authenticate::class,
        'guest'    => RedirectIfAuthenticated::class,
        'throttle' => ThrottleRequests::class,
        'csrf'     => VerifyCsrfToken::class,
        'headers'  => SecurityHeaders::class,
    ];

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
