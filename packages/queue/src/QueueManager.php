<?php

namespace Kyqo\Queue;

/**
 * Kyqo Queue Manager
 *
 * FIX m4: dispatch() now verifies that the handle() method is public before
 * invoking it via reflection. A protected or private handle() would cause a
 * fatal Error at runtime; instead a BadMethodCallException is thrown early
 * with a clear diagnostic message.
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
     * FIX m4: ReflectionMethod::isPublic() is checked before invocation.
     * A non-public handle() raises BadMethodCallException immediately, with
     * a clear message, rather than a cryptic Error at call time.
     *
     * @throws \BadMethodCallException if handle() is absent or not public.
     * @throws \RuntimeException       if a primitive parameter cannot be resolved.
     */
    public function dispatch(object $job): mixed
    {
        if (!method_exists($job, 'handle')) {
            throw new \BadMethodCallException(
                get_class($job) . '::handle() does not exist.'
            );
        }

        $method = new \ReflectionMethod($job, 'handle');

        // FIX m4: reject non-public handle() early.
        if (!$method->isPublic()) {
            throw new \BadMethodCallException(
                get_class($job) . '::handle() must be public to be dispatched.'
            );
        }

        $app    = \Kyqo\Core\Application::getInstance();
        $params = $method->getParameters();
        $args   = [];

        foreach ($params as $param) {
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
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
