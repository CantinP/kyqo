<?php

namespace Kyqo\Cache\Stores;

use Kyqo\Cache\StoreInterface;

/**
 * Redis cache store (ext-redis).
 *
 * Config keys expected:
 *   host     (default: 127.0.0.1)
 *   port     (default: 6379)
 *   password (optional)
 *   database (default: 0)
 *   prefix   (default: 'kyqo:')
 *   timeout  (default: 2.0)
 *
 * FIX AUDIT-4: Connection is lazy — the \Redis object is created on the first
 *              actual cache operation, not in __construct().
 *              This means a misconfigured or unavailable Redis server will NOT
 *              crash the application at boot time; it will only throw when
 *              the cache is actually used, producing a clear error in context.
 */
class RedisStore implements StoreInterface
{
    protected ?\Redis $redis = null;
    protected string  $prefix;
    protected array   $config;

    public function __construct(array $config)
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('ext-redis is required to use the Redis cache driver.');
        }

        $this->config = $config;
        $this->prefix = $config['prefix'] ?? 'kyqo:';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->connection()->get($this->prefix . $key);
        if ($raw === false) return $default;
        return unserialize($raw, ['allowed_classes' => true]);
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $serialized = serialize($value);
        if ($ttl > 0) {
            return (bool) $this->connection()->setex($this->prefix . $key, $ttl, $serialized);
        }
        return (bool) $this->connection()->set($this->prefix . $key, $serialized);
    }

    public function forget(string $key): bool
    {
        return $this->connection()->del($this->prefix . $key) >= 0;
    }

    public function has(string $key): bool
    {
        return (bool) $this->connection()->exists($this->prefix . $key);
    }

    public function flush(): bool
    {
        return (bool) $this->connection()->flushDB();
    }

    public function getRedis(): \Redis
    {
        return $this->connection();
    }

    /**
     * FIX AUDIT-4: Lazy connection factory.
     */
    protected function connection(): \Redis
    {
        if ($this->redis !== null) {
            return $this->redis;
        }

        $this->redis = new \Redis();
        $this->redis->connect(
            $this->config['host']    ?? '127.0.0.1',
            (int)   ($this->config['port']    ?? 6379),
            (float) ($this->config['timeout'] ?? 2.0)
        );

        if (!empty($this->config['password'])) {
            $this->redis->auth($this->config['password']);
        }

        $this->redis->select((int) ($this->config['database'] ?? 0));

        return $this->redis;
    }
}
