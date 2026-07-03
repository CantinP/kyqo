<?php

namespace Kyqo\Http\Support\Facades;

use Closure;
use Kyqo\Core\Application;
use Kyqo\Http\Router\Route as RouteInstance;
use Kyqo\Http\Router\Router;

/**
 * Route Facade
 *
 * Proxies all calls to the Router singleton bound in the container.
 * This makes `Route::get()`, `Route::post()`, etc. work in routes/web.php
 * and routes/api.php without needing the $router variable.
 */
class Route
{
    protected static function router(): Router
    {
        return Application::getInstance()->make(Router::class);
    }

    public static function get(string $uri, Closure|array|string $action): RouteInstance
    {
        return static::router()->get($uri, $action);
    }

    public static function post(string $uri, Closure|array|string $action): RouteInstance
    {
        return static::router()->post($uri, $action);
    }

    public static function put(string $uri, Closure|array|string $action): RouteInstance
    {
        return static::router()->put($uri, $action);
    }

    public static function patch(string $uri, Closure|array|string $action): RouteInstance
    {
        return static::router()->patch($uri, $action);
    }

    public static function delete(string $uri, Closure|array|string $action): RouteInstance
    {
        return static::router()->delete($uri, $action);
    }

    public static function options(string $uri, Closure|array|string $action): RouteInstance
    {
        return static::router()->options($uri, $action);
    }

    public static function any(string $uri, Closure|array|string $action): RouteInstance
    {
        return static::router()->any($uri, $action);
    }

    public static function match(array $methods, string $uri, Closure|array|string $action): RouteInstance
    {
        return static::router()->match($methods, $uri, $action);
    }

    public static function group(array $attributes, Closure $callback): void
    {
        static::router()->group($attributes, $callback);
    }

    public static function prefix(string $prefix): RouteGroupBuilder
    {
        return new RouteGroupBuilder(static::router(), ['prefix' => $prefix]);
    }

    public static function middleware(string|array $middleware): RouteGroupBuilder
    {
        return new RouteGroupBuilder(static::router(), [
            'middleware' => is_array($middleware) ? $middleware : [$middleware],
        ]);
    }

    public static function resource(string $name, string $controller): void
    {
        static::router()->resource($name, $controller);
    }

    public static function url(string $name, array $parameters = []): string
    {
        return static::router()->url($name, $parameters);
    }

    public static function getRoutes(): array
    {
        return static::router()->getRoutes();
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        return static::router()->$method(...$args);
    }
}
