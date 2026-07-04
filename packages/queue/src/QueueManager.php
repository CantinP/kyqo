<?php

namespace Kyqo\Queue;

/**
 * Kyqo Queue Manager
 *
 * FIX AUDIT-7: dispatch() now catches exceptions thrown by job::handle()
 *              and writes them to a `failed_jobs` table (database driver) or
 *              logs them (other drivers), instead of letting the exception
 *              propagate silently or kill the worker.
 *
 * Failed-jobs table schema (create once via migration):
 *
 *   CREATE TABLE IF NOT EXISTS failed_jobs (
 *       id         INTEGER PRIMARY KEY AUTOINCREMENT,
 *       connection VARCHAR(255) NOT NULL,
 *       queue      VARCHAR(255) NOT NULL DEFAULT 'default',
 *       payload    TEXT         NOT NULL,
 *       exception  TEXT         NOT NULL,
 *       failed_at  INTEGER      NOT NULL
 *   );
 *
 * Config key `queue.failed.database` controls the DSN for the failed-jobs
 * table (defaults to the same DSN as the default database queue connection).
 * Set `queue.failed.driver` to 'null' to disable failed-job recording.
 *
 * FIX AUDIT-8 (note): recordFailedJob() uses serialize() to capture the job
 * payload. PHP's serialize() will emit an E_NOTICE (PHP < 8.1) or throw a
 * \Error (PHP 8.1+ with Fiber/Closure) if the job graph contains a Closure
 * or resource. If your jobs may contain closures, either:
 *   a) implement __serialize()/__unserialize() on the job to exclude them, or
 *   b) swap serialize() for json_encode() with a custom normaliser.
 * recordFailedJob() wraps the call in a try/catch so this will never mask
 * the original dispatch exception.
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
     * Dispatch a job synchronously, injecting handle() dependencies.
     *
     * FIX AUDIT-7: On exception, record the job in failed_jobs and
     *              re-throw so the caller (e.g. the worker loop) knows
     *              the job failed.
     */
    public function dispatch(object $job): mixed
    {
        if (!method_exists($job, 'handle')) {
            throw new \BadMethodCallException(get_class($job) . '::handle() does not exist.');
        }

        $method = new \ReflectionMethod($job, 'handle');

        if (!$method->isPublic()) {
            throw new \BadMethodCallException(get_class($job) . '::handle() must be public to be dispatched.');
        }

        $app    = \Kyqo\Core\Application::getInstance();
        $params = $method->getParameters();
        $args   = [];

        foreach ($params as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
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

        try {
            return $job->handle(...$args);
        } catch (\Throwable $e) {
            $this->recordFailedJob($job, $e);
            throw $e;
        }
    }

    // ── Failed-job recording ─────────────────────────────────────────────────

    /**
     * FIX AUDIT-7: Persist the failed job.
     *
     * Writes to the `failed_jobs` table when a DB DSN is configured,
     * otherwise logs via the Logger ('log' alias) or error_log() fallback.
     * Never throws — a failure inside the failure handler must not mask the
     * original exception.
     *
     * FIX AUDIT-8: serialize() is wrapped in its own try/catch so that a
     * job containing a Closure or resource cannot prevent the failure from
     * being recorded. On serialize failure we fall back to get_class($job).
     */
    protected function recordFailedJob(object $job, \Throwable $e): void
    {
        $failedConfig = $this->config['failed'] ?? [];
        $driver       = $failedConfig['driver'] ?? 'database';

        if ($driver === 'null') {
            return;
        }

        $connectionName = $this->config['default'] ?? 'sync';
        $queue          = $this->config['connections'][$connectionName]['queue'] ?? 'default';

        // FIX AUDIT-8: guard serialize() against Closure/resource in job graph.
        try {
            $payload = serialize($job);
        } catch (\Throwable) {
            $payload = get_class($job) . ' (not serializable)';
        }

        $exception = sprintf(
            '%s: %s in %s:%d\n%s',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        $failedAt = time();

        // Try persisting to DB.
        try {
            $dsn = $failedConfig['dsn']
                ?? $this->config['connections'][$connectionName]['dsn']
                ?? null;

            if ($dsn !== null) {
                $pdo = new \PDO($dsn,
                    $failedConfig['username'] ?? ($this->config['connections'][$connectionName]['username'] ?? null),
                    $failedConfig['password'] ?? ($this->config['connections'][$connectionName]['password'] ?? null),
                    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                );
                $stmt = $pdo->prepare(
                    'INSERT INTO failed_jobs (connection, queue, payload, exception, failed_at)
                     VALUES (:connection, :queue, :payload, :exception, :failed_at)'
                );
                $stmt->execute([
                    ':connection' => $connectionName,
                    ':queue'      => $queue,
                    ':payload'    => $payload,
                    ':exception'  => $exception,
                    ':failed_at'  => $failedAt,
                ]);
                return;
            }
        } catch (\Throwable) {
            // DB write failed — fall through to logger.
        }

        // Fallback: log it.
        $logMessage = sprintf(
            'Failed job [%s]: %s in %s:%d',
            get_class($job),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        try {
            \Kyqo\Core\Application::getInstance()->make('log')->error($logMessage, [
                'exception' => get_class($e),
                'trace'     => $e->getTraceAsString(),
            ]);
        } catch (\Throwable) {
            error_log($logMessage);
        }
    }

    protected function resolve(string $name): QueueInterface
    {
        $config = $this->config['connections'][$name]
            ?? throw new \InvalidArgumentException("Queue connection [{$name}] not defined.");

        return match ($config['driver']) {
            'sync'     => new Drivers\SyncQueue(),
            'database' => new Drivers\DatabaseQueue($config),
            'redis'    => new Drivers\RedisQueue($config),
            default    => throw new \InvalidArgumentException(
                "Queue driver [{$config['driver']}] not supported."
            ),
        };
    }
}
