<?php

namespace Kyqo\Core\Support\Facades;

/**
 * @method static string version()
 * @method static bool isDebug()
 * @method static string environment()
 * @method static mixed make(string $abstract, array $parameters = [])
 * @method static void bind(string $abstract, \Closure|string $concrete)
 * @method static void singleton(string $abstract, \Closure|string $concrete)
 * @method static void instance(string $abstract, mixed $instance)
 */
class App extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'app';
    }
}
