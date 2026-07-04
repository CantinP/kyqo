<?php

namespace Kyqo\Cache\Stores;

use Kyqo\Cache\StoreInterface;

/**
 * Memcached cache store (ext-memcached).
 *
 * Config keys:
 *   servers         — array of ['host', 'port', 'weight']
 *   prefix          — key prefix (default: 'kyqo:')
 *   allowed_classes — passed to unserialize() (default: false).
 *                     Set to true or an array of FQCNs if the cached
 *                     values contain serialized objects that must be
 *                     restored as classed instances.
 *
 * FIX AUDIT-10: unserialize() now uses config['allowed_classes'] (default
 *               false) instead of the hard-coded true that was present
 *               before. Mirrors RedisStore and RedisQueue behaviour.
 *
 * Example config:
 *   'memcached' => [
 *       'driver'          => 'memcached',
 *       'prefix'          => 'myapp:',
 *       'allowed_classes' => false,
 *       'servers'         => [
 *           ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100],
 *       ],
 *   ],
 */
class MemcachedStore implements StoreInterface
{
    protected \Memcached $memcached;
    protected string     $prefix;
    protected array      $config;

    public function __construct(array $config)
    {
        if (!extension_loaded('memcached')) {
            throw new \RuntimeException('ext-memcached is required to use the Memcached cache driver.');
        }

        $this->config    = $config;
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

        // FIX AUDIT-10: respect allowed_classes from config (default false).
        $allowed = $this->config['allowed_classes'] ?? false;
        return unserialize($raw, ['allowed_classes' => $allowed]);
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
