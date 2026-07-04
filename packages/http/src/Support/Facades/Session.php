<?php

namespace Kyqo\Http\Support\Facades;

use Kyqo\Core\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void put(string $key, mixed $value)
 * @method static bool has(string $key)
 * @method static void forget(string $key)
 * @method static void flush()
 * @method static void flash(string $key, mixed $value)
 * @method static mixed pull(string $key, mixed $default = null)
 * @method static void regenerate(bool $deleteOld = false)
 */
class Session extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'session';
    }
}
