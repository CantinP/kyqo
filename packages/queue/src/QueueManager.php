<?php

namespace Kyqo\Queue;

/**
 * Kyqo Queue Manager
 *
 * BUG-V4-2: dispatch() no longer calls app()->call() which didn't exist.
 * Jobs are now executed via SyncQueue::execute() pattern or direct handle().
 */
class QueueManager
{
    protected array $config;
    protected array $connections = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function connection(?string $name = null): QueueInterface
    {
        $name ??= $this->config['default'] ?? 'sync';

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolve($name);
        }

        return $this->connections[$name];
    }

    public function push(object $job, ?string $queue = null): mixed
    {
        return $this->connection()->push($job, $queue);
    }

    public function later(\DateTimeInterface|int $delay, object $job, ?string $queue = null): mixed
    {
        return $this->connection()->later($delay, $job, $queue);
    }

    /**
     * Dispatch a job immediately, resolving its handle() method directly.
     *
     * BUG-V4-2 FIX: Removed app()->call() (method did not exist on Container).
     * Dependencies are injected via Container::make() on each parameter type.
     */
    public function dispatch(object $job): mixed
    {
        if (!method_exists($job, 'handle')) {
            return null;
        }

        try {
            $app    = \Kyqo\Core\Application::getInstance();
            $method = new \ReflectionMethod($job, 'handle');
            $params = $method->getParameters();
            $args   = [];
            foreach ($params as $param) {
                $type = $param->getType();
                if ($type && !$type->isBuiltin()) {
                    $args[] = $app->make($type->getName());
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    $args[] = null;
                }
            }
            return $job->handle(...$args);
        } catch (\Throwable) {
            // Fallback: call with no arguments
            return $job->handle();
        }
    }

    protected function resolve(string $name): QueueInterface
    {
        $config = $this->config['connections'][$name]
            ?? throw new \InvalidArgumentException("Queue connection [{$name}] not defined.");

        return match ($config['driver']) {
            'sync'     => new Drivers\SyncQueue(),
            'database' => new Drivers\DatabaseQueue($config),
            default    => throw new \InvalidArgumentException(
                "Queue driver [{$config['driver']}] not supported."
            ),
        };
    }
}
