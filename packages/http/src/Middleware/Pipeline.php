<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Core\Application;
use Kyqo\Http\Request;

/**
 * Middleware Pipeline
 *
 * FIX C2: resolveMiddleware() now validates:
 *   1. The class exists.
 *   2. The resolved instance has a handle() method.
 * If either check fails, a RuntimeException is thrown instead of silently
 * returning a broken object or calling new on an unknown class.
 *
 * The container fallback (new $class()) is kept only for zero-dependency
 * middleware in containerless test environments, but is now guarded.
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
     * FIX C2: resolve with full validation.
     *
     * Resolution order:
     *   1. Application container (preferred — supports constructor injection).
     *   2. Direct instantiation fallback (zero-arg middleware only, test contexts).
     *
     * After resolution, verify the instance has handle() — throws if not.
     *
     * @throws \RuntimeException if class does not exist or has no handle() method.
     */
    protected function resolveMiddleware(string $class): object
    {
        if (!class_exists($class)) {
            throw new \RuntimeException(
                "Middleware class [{$class}] does not exist."
            );
        }

        $instance = null;

        try {
            $app      = Application::getInstance();
            $instance = $app->make($class);
        } catch (\Throwable) {
            // Fallback for zero-dependency middleware in containerless contexts
            $instance = new $class();
        }

        if (!method_exists($instance, 'handle')) {
            throw new \RuntimeException(
                "Middleware [{$class}] does not implement a handle() method."
            );
        }

        return $instance;
    }
}
