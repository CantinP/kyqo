<?php

namespace Kyqo\Http\Support\Facades;

use Kyqo\Core\Support\Facades\Facade;

/**
 * @method static \Kyqo\Http\Router\Route get(string $uri, mixed $action)
 * @method static \Kyqo\Http\Router\Route post(string $uri, mixed $action)
 * @method static \Kyqo\Http\Router\Route put(string $uri, mixed $action)
 * @method static \Kyqo\Http\Router\Route patch(string $uri, mixed $action)
 * @method static \Kyqo\Http\Router\Route delete(string $uri, mixed $action)
 * @method static \Kyqo\Http\Router\Route options(string $uri, mixed $action)
 * @method static \Kyqo\Http\Router\Route any(string $uri, mixed $action)
 * @method static \Kyqo\Http\Router\Router middleware(string|array $middleware)
 * @method static \Kyqo\Http\Router\Router prefix(string $prefix)
 * @method static void group(array|\Closure $attributesOrCallback, ?\Closure $callback = null)
 * @method static mixed dispatch(\Kyqo\Http\Request $request)
 */
class Route extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'router';
    }
}
