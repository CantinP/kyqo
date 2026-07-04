<?php

namespace Kyqo\Queue\Drivers;

use Kyqo\Queue\QueueInterface;

/**
 * Redis queue driver (ext-redis).
 *
 * Uses a Redis LIST as a FIFO queue:
 *   push / later  → RPUSH
 *   pop           → LPOP  (with BRPOPLPUSH for optional safe processing)
 *
 * Delayed jobs are stored in a Sorted Set keyed by available_at score
 * and migrated to the main LIST on each pop() call.
 *
 * Config keys:
 *   host       (default: 127.0.0.1)
 *   port       (default: 6379)
 *   password   (optional)
 *   database   (default: 0)
 *   prefix     (default: 'kyqo:queue:')
 *   queue      (default: 'default')
 *   timeout    (default: 2.0)
 *
 * FIX AUDIT-4: Connection is lazy — \Redis is created on first use,
 *              not in __construct(), so a down Redis server does not
 *              crash the application at boot time.
 */
class RedisQueue implements QueueInterface
{
    protected ?\Redis $redis = null;
    protected array   $config;
    protected string  $prefix;
    protected string  $defaultQueue;

    public function __construct(array $config)
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('ext-redis is required to use the Redis queue driver.');
        }

        $this->config       = $config;
        $this->prefix       = $config['prefix'] ?? 'kyqo:queue:';
        $this->defaultQueue = $config['queue']  ?? 'default';
    }

    public function push(object $job, ?string $queue = null): mixed
    {
        $key = $this->listKey($queue);
        return $this->connection()->rPush($key, $this->serialize($job, time()));
    }

    public function later(\DateTimeInterface|int $delay, object $job, ?string $queue = null): mixed
    {
        $availableAt = $delay instanceof \DateTimeInterface
            ? $delay->getTimestamp()
            : time() + (int) $delay;

        $key = $this->delayedKey($queue);
        return $this->connection()->zAdd($key, $availableAt, $this->serialize($job, $availableAt));
    }

    /**
     * Migrate any due delayed jobs then LPOP the next ready job.
     */
    public function pop(?string $queue = null): ?object
    {
        $this->migrateDelayed($queue);

        $raw = $this->connection()->lPop($this->listKey($queue));
        if ($raw === false || $raw === null) {
            return null;
        }

        $payload = json_decode($raw, true);
        if (!isset($payload['job'])) {
            return null;
        }

        return unserialize($payload['job'], ['allowed_classes' => true]) ?: null;
    }

    public function size(?string $queue = null): int
    {
        $this->migrateDelayed($queue);
        return (int) $this->connection()->lLen($this->listKey($queue));
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    protected function migrateDelayed(?string $queue): void
    {
        $now     = time();
        $delayed = $this->delayedKey($queue);
        $list    = $this->listKey($queue);

        $jobs = $this->connection()->zRangeByScore($delayed, '-inf', (string) $now);
        if (empty($jobs)) return;

        foreach ($jobs as $job) {
            $this->connection()->rPush($list, $job);
        }
        $this->connection()->zRemRangeByScore($delayed, '-inf', (string) $now);
    }

    protected function serialize(object $job, int $availableAt): string
    {
        return json_encode([
            'job'          => serialize($job),
            'available_at' => $availableAt,
            'pushed_at'    => time(),
        ], JSON_THROW_ON_ERROR);
    }

    protected function listKey(?string $queue): string
    {
        return $this->prefix . ($queue ?? $this->defaultQueue);
    }

    protected function delayedKey(?string $queue): string
    {
        return $this->prefix . ($queue ?? $this->defaultQueue) . ':delayed';
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
