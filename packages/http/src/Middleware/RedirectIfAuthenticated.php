<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Http\Request;
use Kyqo\Http\Response;

/**
 * Redirect authenticated users away from guest-only routes (e.g. /login).
 *
 * Register as 'guest' in the Kernel's routeMiddleware.
 */
class RedirectIfAuthenticated
{
    public function handle(Request $request, \Closure $next, string $redirectTo = '/dashboard'): mixed
    {
        try {
            $auth = \Kyqo\Core\Application::getInstance()->make('auth');
            if ($auth->check()) {
                return Response::redirect($redirectTo);
            }
        } catch (\Throwable) {}

        return $next($request);
    }
}
