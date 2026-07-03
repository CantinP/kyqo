<?php

namespace Kyqo\Auth\Middleware;

use Kyqo\Http\Request;
use Kyqo\Http\Response;
use Kyqo\Auth\AuthManager;

/**
 * Authentication Middleware
 *
 * Rejects unauthenticated requests with a 401 response.
 * Routes protected with ->middleware('auth') will fail here
 * if no valid session or token is present.
 */
class Authenticate
{
    protected AuthManager $auth;

    public function __construct(AuthManager $auth)
    {
        $this->auth = $auth;
    }

    public function handle(Request $request, \Closure $next): mixed
    {
        if ($this->auth->guest()) {
            if ($request->wantsJson()) {
                return Response::make(
                    json_encode(['message' => 'Unauthenticated.']),
                    401,
                    ['Content-Type' => 'application/json']
                );
            }

            // Redirect to login for web requests
            return Response::redirect('/login');
        }

        return $next($request);
    }
}
