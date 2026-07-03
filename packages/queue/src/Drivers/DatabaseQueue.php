<?php

namespace Kyqo\Queue\Drivers;

use Kyqo\Queue\QueueInterface;

/**
 * Database-backed queue driver.
 *
 * Jobs are serialized and stored in a `jobs` table.
 * This is a concrete stub — full implementation requires a DB connection.
 *
 * Schema expected:
 *   id, queue, payload (TEXT), attempts, available_at, created_at
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

        $job = @unserialize($row['payload']);
        return is_object($job) ? $job : null;
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
