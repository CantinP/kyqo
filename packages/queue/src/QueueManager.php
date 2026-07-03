<?php

namespace Kyqo\Queue;

/**
 * Kyqo Queue Manager
 *
 * FIX M3: dispatch() no longer swallows exceptions with a silent fallback.
 * If Container::make() fails to inject a dependency, the exception propagates
 * so the developer can see the real error instead of a silent no-op.
 * The zero-argument fallback is removed entirely.
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
     * Dispatch a job immediately by resolving its handle() dependencies via the container.
     *
     * FIX M3: exceptions from dependency resolution now propagate.
     * The previous silent catch (which called handle() with no args as fallback)
     * masked real container/DI errors and made debugging impossible.
     *
     * @throws \BadMethodCallException if the job has no handle() method.
     */
    public function dispatch(object $job): mixed
    {
        if (!method_exists($job, 'handle')) {
            throw new \BadMethodCallException(
                get_class($job) . '::handle() does not exist.'
            );
        }

        $app    = \Kyqo\Core\Application::getInstance();
        $method = new \ReflectionMethod($job, 'handle');
        $params = $method->getParameters();
        $args   = [];

        foreach ($params as $param) {
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                // Throws if the service is not bound — surfaces the real error
                $args[] = $app->make($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException(
                    'Cannot resolve primitive parameter $' . $param->getName()
                    . ' for ' . get_class($job) . '::handle()'
                );
            }
        }

        return $job->handle(...$args);
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
