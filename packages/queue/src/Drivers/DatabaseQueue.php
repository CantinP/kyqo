<?php

namespace Kyqo\Queue\Drivers;

use Kyqo\Queue\QueueInterface;

/**
 * Database-backed queue driver.
 *
 * FIX C2: pop() now uses unserialize() with an explicit allowed_classes
 * whitelist to prevent PHP Object Injection attacks.
 * The default whitelist is empty (no classes allowed) — callers must
 * configure allowed_classes via the queue config:
 *   'connections' => ['database' => ['driver' => 'database', 'allowed_classes' => [MyJob::class, ...]]]
 *
 * Pass allowed_classes = true only if you fully trust the queue storage.
 */
class DatabaseQueue implements QueueInterface
{
    protected array $config;
    protected ?\PDO $pdo = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function push(object $job, ?string $queue = null): mixed
    {
        $queue = $queue ?? $this->config['queue'] ?? 'default';
        return $this->insertJob($job, $queue, time());
    }

    public function later(\DateTimeInterface|int $delay, object $job, ?string $queue = null): mixed
    {
        $queue       = $queue ?? $this->config['queue'] ?? 'default';
        $availableAt = $delay instanceof \DateTimeInterface
            ? $delay->getTimestamp()
            : time() + $delay;
        return $this->insertJob($job, $queue, $availableAt);
    }

    public function pop(?string $queue = null): ?object
    {
        $pdo   = $this->connection();
        $queue = $queue ?? $this->config['queue'] ?? 'default';

        $stmt = $pdo->prepare(
            'SELECT * FROM jobs WHERE queue = :queue AND available_at <= :now ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([':queue' => $queue, ':now' => time()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $del = $pdo->prepare('DELETE FROM jobs WHERE id = :id');
        $del->execute([':id' => $row['id']]);

        return $this->deserializeJob($row['payload']);
    }

    public function size(?string $queue = null): int
    {
        $pdo   = $this->connection();
        $queue = $queue ?? $this->config['queue'] ?? 'default';
        $stmt  = $pdo->prepare('SELECT COUNT(*) FROM jobs WHERE queue = :queue AND available_at <= :now');
        $stmt->execute([':queue' => $queue, ':now' => time()]);
        return (int) $stmt->fetchColumn();
    }

    protected function insertJob(object $job, string $queue, int $availableAt): int|string
    {
        $pdo  = $this->connection();
        $stmt = $pdo->prepare(
            'INSERT INTO jobs (queue, payload, attempts, available_at, created_at) VALUES (:queue, :payload, 0, :available_at, :created_at)'
        );
        $stmt->execute([
            ':queue'        => $queue,
            ':payload'      => serialize($job),
            ':available_at' => $availableAt,
            ':created_at'   => time(),
        ]);
        return $pdo->lastInsertId();
    }

    /**
     * FIX C2: deserialize with an explicit class whitelist.
     *
     * Config key `allowed_classes`:
     *   - false (default) : no classes allowed — safest for untrusted storage
     *   - true            : all classes allowed — only use with trusted storage
     *   - string[]        : explicit whitelist of class names
     *
     * @throws \UnexpectedValueException if deserialization yields a non-object.
     */
    protected function deserializeJob(string $payload): ?object
    {
        $allowedClasses = $this->config['allowed_classes'] ?? false;

        $job = unserialize($payload, ['allowed_classes' => $allowedClasses]);

        if ($job === false) {
            return null;
        }

        if (!is_object($job)) {
            throw new \UnexpectedValueException(
                'Deserialized queue payload is not an object. ' .
                'Set allowed_classes in queue config to enable class deserialization.'
            );
        }

        return $job;
    }

    protected function connection(): \PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dsn      = $this->config['dsn']      ?? throw new \RuntimeException('Queue database DSN not configured.');
        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;

        $this->pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return $this->pdo;
    }
}
