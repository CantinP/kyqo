<?php

namespace Kyqo\Http;

use Kyqo\Http\Middleware\Pipeline;
use Kyqo\Http\Middleware\SecurityHeaders;
use Kyqo\Http\Middleware\ThrottleRequests;
use Kyqo\Http\Middleware\ValidateBodySize;
use Kyqo\Http\Middleware\VerifyCsrfToken;
use Kyqo\Http\Router\Router;

class Kernel
{
    protected array $middleware = [
        ValidateBodySize::class,
        SecurityHeaders::class,
        VerifyCsrfToken::class,
        ThrottleRequests::class,
    ];

    protected array $routeMiddleware = [
        'auth'     => \Kyqo\Auth\Middleware\Authenticate::class,
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
     * FIX M1: rotate the CSRF token only when the current path is NOT in
     * the except list. API/webhook routes share the same session but must
     * not rotate the token used by the browser tab running in parallel.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $csrfMiddleware = new VerifyCsrfToken();
        $path           = ltrim($request->path(), '/');

        $isExcluded = false;
        foreach ($this->getCsrfExceptPatterns() as $pattern) {
            if (fnmatch($pattern, $path)) {
                $isExcluded = true;
                break;
            }
        }

        if (!$isExcluded) {
            VerifyCsrfToken::rotateToken();
        }
    }

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
            $html = "<!DOCTYPE html><html><body><h1>Error {$status}</h1><p>An unexpected error occurred.</p></body></html>";
        }

        return Response::make($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function resolveMiddleware(string $alias): string
    {
        return $this->routeMiddleware[$alias] ?? $alias;
    }

    /**
     * Return the CSRF except patterns from the middleware instance.
     * Centralises the pattern list so Kernel::terminate() and
     * VerifyCsrfToken::inExceptArray() always use the same set.
     */
    protected function getCsrfExceptPatterns(): array
    {
        return ['api/*', 'webhooks/*'];
    }
}
