<?php

namespace Kyqo\Http\Support\Facades;

use Closure;
use Kyqo\Http\Router\Route as RouteInstance;
use Kyqo\Http\Router\Router;

/**
 * Fluent builder for route groups.
 *
 * FIX #1: wrapRoute() now registers the route INSIDE the group closure
 * so prefix and middleware attributes are correctly applied.
 *
 * Allows:
 *   Route::prefix('admin')->middleware('auth')->group(fn () => ...);
 *   Route::middleware('auth')->prefix('api/v1')->get('/users', ...);
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

    public function put(string $uri, Closure|array|string $action): RouteInstance
    {
        return $this->wrapRoute(fn () => $this->router->put($uri, $action));
    }

    public function patch(string $uri, Closure|array|string $action): RouteInstance
    {
        return $this->wrapRoute(fn () => $this->router->patch($uri, $action));
    }

    public function delete(string $uri, Closure|array|string $action): RouteInstance
    {
        return $this->wrapRoute(fn () => $this->router->delete($uri, $action));
    }

    /**
     * FIX #1: Route is registered INSIDE the group so Router::$groupStack
     * is active when addRoute() runs, correctly applying prefix + middleware.
     */
    protected function wrapRoute(Closure $register): RouteInstance
    {
        $route = null;
        $this->router->group($this->attributes, function () use ($register, &$route) {
            $route = $register();
        });
        return $route;
    }
}
