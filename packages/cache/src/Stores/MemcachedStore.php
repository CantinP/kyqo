<?php

namespace Kyqo\Cache\Stores;

use Kyqo\Cache\StoreInterface;

/**
 * Memcached cache store (ext-memcached).
 *
 * Config keys:
 *   servers  — array of ['host', 'port', 'weight']
 *   prefix   — key prefix (default: 'kyqo:')
 *
 * Example config:
 *   'memcached' => [
 *       'driver'  => 'memcached',
 *       'prefix'  => 'myapp:',
 *       'servers' => [
 *           ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100],
 *       ],
 *   ],
 */
class MemcachedStore implements StoreInterface
{
    protected \Memcached $memcached;
    protected string     $prefix;

    public function __construct(array $config)
    {
        if (!extension_loaded('memcached')) {
            throw new \RuntimeException('ext-memcached is required to use the Memcached cache driver.');
        }

        $this->prefix    = $config['prefix'] ?? 'kyqo:';
        $this->memcached = new \Memcached();

        foreach ($config['servers'] ?? [['host' => '127.0.0.1', 'port' => 11211]] as $server) {
            $this->memcached->addServer(
                $server['host']   ?? '127.0.0.1',
                (int) ($server['port']   ?? 11211),
                (int) ($server['weight'] ?? 100)
            );
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->memcached->get($this->prefix . $key);
        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return $default;
        }
        return unserialize($raw, ['allowed_classes' => true]);
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->memcached->set(
            $this->prefix . $key,
            serialize($value),
            $ttl > 0 ? time() + $ttl : 0
        );
    }

    public function forget(string $key): bool
    {
        $this->memcached->delete($this->prefix . $key);
        return $this->memcached->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    public function has(string $key): bool
    {
        $this->memcached->get($this->prefix . $key);
        return $this->memcached->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    public function flush(): bool
    {
        return $this->memcached->flush();
    }
}
