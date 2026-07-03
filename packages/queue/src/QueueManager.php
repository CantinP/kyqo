<?php

namespace Kyqo\Queue;

/**
 * Kyqo Queue Manager
 *
 * Manages queue connections and job dispatching.
 * Supports sync, database and redis queue drivers.
 */
class QueueManager
{
    protected array $config;
    protected array $connections = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Get a queue connection.
     */
    public function connection(?string $name = null): QueueInterface
    {
        $name ??= $this->config['default'] ?? 'sync';

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolve($name);
        }

        return $this->connections[$name];
    }

    /**
     * Push a job onto the queue.
     */
    public function push(object $job, ?string $queue = null): mixed
    {
        return $this->connection()->push($job, $queue);
    }

    /**
     * Push a job to run after a delay.
     */
    public function later(\DateTimeInterface|int $delay, object $job, ?string $queue = null): mixed
    {
        return $this->connection()->later($delay, $job, $queue);
    }

    /**
     * Dispatch a job immediately (sync).
     */
    public function dispatch(object $job): mixed
    {
        if (method_exists($job, 'handle')) {
            return app()->call([$job, 'handle']);
        }
        return null;
    }

    protected function resolve(string $name): QueueInterface
    {
        $config = $this->config['connections'][$name] ?? throw new \InvalidArgumentException("Queue connection [{$name}] not defined.");

        return match ($config['driver']) {
            'sync'     => new Drivers\SyncQueue(),
            'database' => new Drivers\DatabaseQueue($config),
            default    => throw new \InvalidArgumentException("Queue driver [{$config['driver']}] not supported."),
        };
    }
}
