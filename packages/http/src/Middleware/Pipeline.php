<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Core\Application;
use Kyqo\Http\Request;

/**
 * Middleware Pipeline
 *
 * Resolves middleware through the Application container when available,
 * so middleware can have constructor dependencies injected.
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
     * Resolve a middleware class through the container if possible,
     * otherwise fall back to direct instantiation.
     */
    protected function resolveMiddleware(string $class): object
    {
        try {
            return Application::getInstance()->make($class);
        } catch (\Throwable) {
            return new $class();
        }
    }
}
