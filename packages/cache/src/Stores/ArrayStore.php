<?php

namespace Kyqo\Cache\Stores;

use Kyqo\Cache\StoreInterface;

/**
 * In-memory array cache store.
 *
 * FIX m1: has() now checks key existence and TTL directly, independently
 * of the stored value. The previous `$this->get($key) !== null` incorrectly
 * returned false when a legitimate null value was cached.
 */
class ArrayStore implements StoreInterface
{
    /** @var array<string, array{value: mixed, expires_at: int}> */
    protected array $storage = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->readEntry($key);
        return $item !== null ? $item['value'] : $default;
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

    /**
     * FIX m1: key exists as long as the entry is present and not expired,
     * regardless of whether the stored value is null.
     */
    public function has(string $key): bool
    {
        return $this->readEntry($key) !== null;
    }

    public function flush(): bool
    {
        $this->storage = [];
        return true;
    }

    /**
     * Shared TTL-aware entry reader.
     * Returns the raw array on hit, null on miss or expiry.
     * Evicts expired entries on read (lazy expiry).
     */
    private function readEntry(string $key): ?array
    {
        if (!isset($this->storage[$key])) {
            return null;
        }
        $item = $this->storage[$key];
        if ($item['expires_at'] !== 0 && $item['expires_at'] <= time()) {
            unset($this->storage[$key]);
            return null;
        }
        return $item;
    }
}
