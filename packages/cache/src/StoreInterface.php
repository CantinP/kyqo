<?php

namespace Kyqo\Cache;

/**
 * Contract for all cache stores.
 */
interface StoreInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function put(string $key, mixed $value, int $ttl = 3600): bool;
    public function forget(string $key): bool;
    public function has(string $key): bool;
    public function flush(): bool;
}
