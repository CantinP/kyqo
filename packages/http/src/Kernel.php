<?php

namespace Kyqo\Http;

use Kyqo\Http\Middleware\Pipeline;
use Kyqo\Http\Middleware\SecurityHeaders;
use Kyqo\Http\Middleware\ThrottleRequests;
use Kyqo\Http\Middleware\ValidateBodySize;
use Kyqo\Http\Middleware\VerifyCsrfToken;

/**
 * HTTP Kernel
 *
 * The central dispatcher for all HTTP requests.
 * Every request passes through the global middleware stack
 * before reaching the router, and every response passes back through it.
 *
 * SECURITY: Global middleware is applied unconditionally to ALL requests.
 * Route-level middleware can be added via the $routeMiddleware map.
 */
class Kernel
{
    /**
     * Global middleware applied to every HTTP request.
     * Order matters: runs top-to-bottom on request, bottom-to-top on response.
     */
    protected array $middleware = [
        ValidateBodySize::class,     // 1. Reject oversized bodies first
        SecurityHeaders::class,      // 2. Inject security headers on every response
        VerifyCsrfToken::class,      // 3. Validate CSRF on state-mutating requests
        ThrottleRequests::class,     // 4. Rate-limit by IP+route
    ];

    /**
     * Named middleware available to individual routes.
     */
    protected array $routeMiddleware = [
        'auth'       => \Kyqo\Auth\Middleware\Authenticate::class,
        'throttle'   => ThrottleRequests::class,
        'csrf'       => VerifyCsrfToken::class,
        'headers'    => SecurityHeaders::class,
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
                ->then(fn (Request $req) => $this->router->dispatch($req->method(), $req->uri()));

            return $response instanceof Response ? $response : Response::make((string) $response);
        } catch (\Throwable $e) {
            return $this->handleException($request, $e);
        }
    }

    /**
     * Terminate the kernel after the response has been sent.
     * Called to run cleanup tasks (logging, session save, etc.).
     */
    public function terminate(Request $request, Response $response): void
    {
        // Rotate CSRF token after each successful response
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
        $debug = (bool) ($this->config['debug'] ?? false);
        $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

        if ($request->wantsJson()) {
            $body = ['message' => $debug ? $e->getMessage() : 'Server Error'];
            if ($debug) {
                $body['trace'] = array_slice(explode("\n", $e->getTraceAsString()), 0, 10);
            }
            return Response::make(json_encode($body), $status, ['Content-Type' => 'application/json']);
        }

        if ($debug) {
            $safe  = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $trace = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html  = "<h1>Error {$status}</h1><pre>{$safe}\n\n{$trace}</pre>";
        } else {
            $html = "<h1>Error {$status}</h1><p>An unexpected error occurred. Please try again later.</p>";
        }

        return Response::make($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Resolve a route middleware by its alias.
     */
    public function resolveMiddleware(string $alias): string
    {
        return $this->routeMiddleware[$alias] ?? $alias;
    }
}
