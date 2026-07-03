<?php

namespace Kyqo\Http\Support\Facades;

use Closure;
use Kyqo\Http\Router\Route as RouteInstance;
use Kyqo\Http\Router\Router;

/**
 * Fluent builder for route groups.
 *
 * Allows:
 *   Route::prefix('admin')->middleware('auth')->group(function () { ... });
 *   Route::middleware('auth')->prefix('api/v1')->group(function () { ... });
 */
class RouteGroupBuilder
{
    public function __construct(
        protected Router $router,
        protected array  $attributes = []
    ) {}

    public function prefix(string $prefix): static
    {
        $this->attributes['prefix'] = $prefix;
        return $this;
    }

    public function middleware(string|array $middleware): static
    {
        $this->attributes['middleware'] = array_merge(
            $this->attributes['middleware'] ?? [],
            is_array($middleware) ? $middleware : [$middleware]
        );
        return $this;
    }

    public function name(string $name): static
    {
        $this->attributes['name'] = $name;
        return $this;
    }

    public function group(Closure $callback): void
    {
        $this->router->group($this->attributes, $callback);
    }

    public function get(string $uri, Closure|array|string $action): RouteInstance
    {
        return $this->wrapRoute(fn () => $this->router->get($uri, $action));
    }

    public function post(string $uri, Closure|array|string $action): RouteInstance
    {
        return $this->wrapRoute(fn () => $this->router->post($uri, $action));
    }

    protected function wrapRoute(Closure $register): RouteInstance
    {
        $this->router->group($this->attributes, function () {});
        return $register();
    }
}
