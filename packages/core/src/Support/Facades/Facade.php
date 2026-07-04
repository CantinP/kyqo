<?php

namespace Kyqo\Core\Support\Facades;

use Kyqo\Core\Application;
use RuntimeException;

/**
 * Base Facade
 *
 * Provides static proxy access to services bound in the IoC container.
 * Each concrete facade declares getFacadeAccessor() returning the
 * container binding key (e.g. 'auth', 'cache', DB::class).
 */
abstract class Facade
{
    protected static array $resolvedInstances = [];

    /**
     * The container binding key this facade proxies.
     */
    abstract protected static function getFacadeAccessor(): string;

    /**
     * Resolve the underlying service from the container.
     */
    protected static function getFacadeRoot(): mixed
    {
        $accessor = static::getFacadeAccessor();

        if (isset(static::$resolvedInstances[$accessor])) {
            return static::$resolvedInstances[$accessor];
        }

        $app = Application::getInstance();
        if ($app === null) {
            throw new RuntimeException(
                'Facade ['.static::class.'] called before the application is bootstrapped.'
            );
        }

        return static::$resolvedInstances[$accessor] = $app->make($accessor);
    }

    /**
     * Clear a resolved facade instance (useful in tests).
     */
    public static function clearResolvedInstance(string $accessor): void
    {
        unset(static::$resolvedInstances[$accessor]);
    }

    /**
     * Clear all resolved facade instances.
     */
    public static function clearResolvedInstances(): void
    {
        static::$resolvedInstances = [];
    }

    /**
     * Forward static calls to the underlying service.
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::getFacadeRoot();

        if ($instance === null) {
            throw new RuntimeException(
                'Facade root for ['.static::getFacadeAccessor().'] resolved to null.'
            );
        }

        return $instance->$method(...$args);
    }
}
