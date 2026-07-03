<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Core\Application;
use Kyqo\Http\Request;

/**
 * Middleware Pipeline
 *
 * FIX N1: resolveMiddleware() now uses Application::make() for ALL middleware
 * (global and route-level), with constructor injection.
 * The catch-all fallback `new $class()` is only used when the container
 * is unavailable (e.g. unit tests without a bootstrapped app), and only
 * for zero-dependency middleware (like SecurityHeaders).
 * For middleware with constructor dependencies (ThrottleRequests, Authenticate),
 * they MUST be bound in the container to work correctly.
 */
class Pipeline
{
    protected Request $request;
    protected array $middleware = [];

    public function send(Request $request): static
    {
        $this->request = $request;
        return $this;
    }

    public function through(array $middleware): static
    {
        $this->middleware = $middleware;
        return $this;
    }

    public function then(\Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            $this->carry(),
            $destination
        );

        return $pipeline($this->request);
    }

    protected function carry(): \Closure
    {
        return function (\Closure $next, $middleware) {
            return function (Request $request) use ($next, $middleware) {
                if (is_string($middleware)) {
                    $middleware = $this->resolveMiddleware($middleware);
                }
                return $middleware->handle($request, $next);
            };
        };
    }

    /**
     * FIX N1: always try the container first so that middleware with
     * constructor dependencies (AuthManager, config, etc.) are properly injected.
     * Falls back to `new $class()` only for zero-dep middleware in test contexts.
     */
    protected function resolveMiddleware(string $class): object
    {
        try {
            $app = Application::getInstance();
            return $app->make($class);
        } catch (\Throwable) {
            // Last resort: only works for middleware with no constructor args.
            return new $class();
        }
    }
}
