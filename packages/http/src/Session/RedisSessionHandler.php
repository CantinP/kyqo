<?php

namespace Kyqo\Http\Session;

/**
 * Session handler backed by Redis.
 *
 * Requires the "redis" extension or a \Redis instance.
 */
class RedisSessionHandler implements \SessionHandlerInterface
{
    private \Redis $redis;
    private int    $ttl;
    private string $prefix;

    public function __construct(\Redis $redis, int $ttl = 7200, string $prefix = 'kyqo_sess:')
    {
        $this->redis  = $redis;
        $this->ttl    = $ttl;
        $this->prefix = $prefix;
    }

    public function open(string $savePath, string $sessionName): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $data = $this->redis->get($this->prefix . $id);
        return $data !== false ? $data : '';
    }

    public function write(string $id, string $data): bool
    {
        return (bool) $this->redis->setEx($this->prefix . $id, $this->ttl, $data);
    }

    public function destroy(string $id): bool
    {
        $this->redis->del($this->prefix . $id);
        return true;
    }

    public function gc(int $maxlifetime): int|false
    {
        // Redis TTL handles expiry automatically.
        return 0;
    }
}
