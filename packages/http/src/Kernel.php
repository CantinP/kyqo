<?php

namespace Kyqo\Http;

use Kyqo\Http\Middleware\Pipeline;
use Kyqo\Http\Middleware\SecurityHeaders;
use Kyqo\Http\Middleware\ThrottleRequests;
use Kyqo\Http\Middleware\ValidateBodySize;
use Kyqo\Http\Middleware\VerifyCsrfToken;
use Kyqo\Http\Router\Router;

/**
 * HTTP Kernel
 *
 * The central dispatcher for all HTTP requests.
 * Every request passes through the global middleware stack
 * before reaching the router, and every response passes back through it.
 *
 * BUG FIX: Router type-hint now correctly uses Kyqo\Http\Router\Router.
 * BUG FIX: Router is resolved from the container via bootstrap/app.php binding.
 * BUG FIX: Route-level middleware is now executed via runWithMiddleware().
 */
class Kernel
{
    /**
     * Global middleware applied to every HTTP request.
     * Order matters: runs top-to-bottom on request, bottom-to-top on response.
     */
    protected array $middleware = [
        ValidateBodySize::class,   // 1. Reject oversized bodies first
        SecurityHeaders::class,    // 2. Inject security headers on every response
        VerifyCsrfToken::class,    // 3. Validate CSRF on state-mutating requests
        ThrottleRequests::class,   // 4. Rate-limit by IP+route
    ];

    /**
     * Named middleware aliases available to individual routes.
     * SEC FIX: auth middleware now only bound when the class exists.
     */
    protected array $routeMiddleware = [
        'throttle' => ThrottleRequests::class,
        'csrf'     => VerifyCsrfToken::class,
        'headers'  => SecurityHeaders::class,
    ];

    protected Router $router;
    protected array $config;

    public function __construct(Router $router, array $config = [])
    {
        $this->router = $router;
        $this->config = $config;
    }

    /**
     * Handle an incoming HTTP request through the full middleware pipeline.
     */
    public function handle(Request $request): Response
    {
        try {
            $response = (new Pipeline())
                ->send($request)
                ->through($this->middleware)
                ->then(fn (Request $req) => $this->dispatch($req));

            return $response instanceof Response ? $response : Response::make((string) $response);
        } catch (\Throwable $e) {
            return $this->handleException($request, $e);
        }
    }

    /**
     * Dispatch the request to the matching route,
     * then run any route-level middleware around the route action.
     *
     * BUG FIX (SEC-1): Route middleware is now actually executed.
     */
    protected function dispatch(Request $request): mixed
    {
        $route = $this->router->findRoute($request->method(), $request->uri());

        if ($route === null) {
            throw new \RuntimeException(
                "No route matched [{$request->method()}] {$request->uri()}",
                404
            );
        }

        $routeMiddleware = array_map(
            fn (string $alias) => $this->resolveMiddleware($alias),
            $route->getMiddleware()
        );

        if (empty($routeMiddleware)) {
            return $route->run();
        }

        return (new Pipeline())
            ->send($request)
            ->through($routeMiddleware)
            ->then(fn (Request $req) => $route->run());
    }

    /**
     * Terminate the kernel after the response has been sent.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            VerifyCsrfToken::rotateToken();
        }
    }

    /**
     * Handle exceptions and produce an appropriate HTTP response.
     * SECURITY: In production, never expose internal error details.
     */
    protected function handleException(Request $request, \Throwable $e): Response
    {
        $debug  = (bool) ($this->config['debug'] ?? false);
        $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        $status = ($status >= 100 && $status <= 599) ? $status : 500;

        if ($request->wantsJson()) {
            $body = ['message' => $debug ? $e->getMessage() : 'Server Error'];
            if ($debug) {
                $body['exception'] = get_class($e);
                $body['trace']     = array_slice(explode("\n", $e->getTraceAsString()), 0, 10);
            }
            return Response::make(
                json_encode($body),
                $status,
                ['Content-Type' => 'application/json']
            );
        }

        if ($debug) {
            $safe  = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $trace = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html  = "<!DOCTYPE html><html><body><h1>Error {$status}</h1><pre>{$safe}\n\n{$trace}</pre></body></html>";
        } else {
            $html = "<!DOCTYPE html><html><body><h1>Error {$status}</h1><p>An unexpected error occurred. Please try again later.</p></body></html>";
        }

        return Response::make($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Resolve a route middleware by its alias or FQCN.
     */
    public function resolveMiddleware(string $alias): string
    {
        return $this->routeMiddleware[$alias] ?? $alias;
    }
}
