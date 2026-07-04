<?php

namespace Kyqo\Core\Support\Facades;

use Kyqo\Core\Hashing\Hasher;

/**
 * Static proxy for the Hasher service.
 *
 * @method static string make(string $value, array $options = [])
 * @method static bool check(string $value, string $hashedValue)
 * @method static bool needsRehash(string $hashedValue, array $options = [])
 */
class Hash extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'hash';
    }
}
