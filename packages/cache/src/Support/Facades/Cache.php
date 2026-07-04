<?php

namespace Kyqo\Cache\Support\Facades;

use Kyqo\Core\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool put(string $key, mixed $value, int $ttl = 3600)
 * @method static bool forget(string $key)
 * @method static bool has(string $key)
 * @method static mixed remember(string $key, int $ttl, \Closure $callback)
 * @method static bool flush()
 * @method static \Kyqo\Cache\StoreInterface store(?string $name = null)
 */
class Cache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cache';
    }
}
