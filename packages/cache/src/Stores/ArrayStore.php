<?php

namespace Kyqo\Cache\Stores;

use Kyqo\Cache\StoreInterface;

/**
 * In-memory array cache store.
 * Items expire based on stored TTL timestamp.
 */
class ArrayStore implements StoreInterface
{
    /** @var array<string, array{value: mixed, expires_at: int}> */
    protected array $storage = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->storage[$key])) {
            return $default;
        }
        $item = $this->storage[$key];
        if ($item['expires_at'] !== 0 && $item['expires_at'] <= time()) {
            unset($this->storage[$key]);
            return $default;
        }
        return $item['value'];
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $this->storage[$key] = [
            'value'      => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
        ];
        return true;
    }

    public function forget(string $key): bool
    {
        unset($this->storage[$key]);
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function flush(): bool
    {
        $this->storage = [];
        return true;
    }
}
