<?php

namespace Kyqo\Cache;

/**
 * Kyqo Cache Manager
 *
 * Manages cache stores and resolves the appropriate driver.
 * Supports file, array, redis, and memcached drivers.
 */
class CacheManager
{
    protected array $config;
    protected array $stores = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Get a cache store.
     */
    public function store(?string $name = null): StoreInterface
    {
        $name ??= $this->config['default'] ?? 'file';

        if (!isset($this->stores[$name])) {
            $this->stores[$name] = $this->resolve($name);
        }

        return $this->stores[$name];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store()->get($key, $default);
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->store()->put($key, $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return $this->store()->forget($key);
    }

    public function has(string $key): bool
    {
        return $this->store()->has($key);
    }

    public function remember(string $key, int $ttl, \Closure $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }
        $value = $callback();
        $this->put($key, $value, $ttl);
        return $value;
    }

    public function flush(): bool
    {
        return $this->store()->flush();
    }

    protected function resolve(string $name): StoreInterface
    {
        $config = $this->config['stores'][$name] ?? throw new \InvalidArgumentException("Cache store [{$name}] not defined.");

        return match ($config['driver']) {
            'file'  => new Stores\FileStore($config['path'] ?? sys_get_temp_dir()),
            'array' => new Stores\ArrayStore(),
            default => throw new \InvalidArgumentException("Cache driver [{$config['driver']}] not supported."),
        };
    }
}
