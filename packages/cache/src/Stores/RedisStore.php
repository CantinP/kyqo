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
 */
class RedisStore implements StoreInterface
{
    protected \Redis $redis;
    protected string $prefix;

    public function __construct(array $config)
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('ext-redis is required to use the Redis cache driver.');
        }

        $this->prefix = $config['prefix'] ?? 'kyqo:';
        $this->redis  = new \Redis();
        $this->redis->connect(
            $config['host']    ?? '127.0.0.1',
            (int)  ($config['port']    ?? 6379),
            (float)($config['timeout'] ?? 2.0)
        );

        if (!empty($config['password'])) {
            $this->redis->auth($config['password']);
        }

        $this->redis->select((int) ($config['database'] ?? 0));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->redis->get($this->prefix . $key);
        if ($raw === false) return $default;
        return unserialize($raw, ['allowed_classes' => true]);
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $serialized = serialize($value);
        if ($ttl > 0) {
            return (bool) $this->redis->setex($this->prefix . $key, $ttl, $serialized);
        }
        return (bool) $this->redis->set($this->prefix . $key, $serialized);
    }

    public function forget(string $key): bool
    {
        return $this->redis->del($this->prefix . $key) >= 0;
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($this->prefix . $key);
    }

    public function flush(): bool
    {
        return (bool) $this->redis->flushDB();
    }

    public function getRedis(): \Redis
    {
        return $this->redis;
    }
}
