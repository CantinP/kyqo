<?php

namespace Kyqo\Queue\Drivers;

use Kyqo\Queue\QueueInterface;

/**
 * Database-backed queue driver.
 *
 * FIX C2 (maintained): unserialize() uses explicit allowed_classes whitelist.
 *
 * FIX minor-3: pop() is now atomic — it uses a reserved_at column to
 * "claim" the job before deleting it, preventing two concurrent workers
 * from processing the same job (race condition).
 *
 * Strategy (works on MySQL, PostgreSQL, SQLite):
 *   1. UPDATE jobs SET reserved_at = NOW(), attempts = attempts + 1
 *      WHERE id = (SELECT MIN(id) FROM jobs WHERE queue=? AND available_at<=? AND reserved_at IS NULL)
 *   2. SELECT the job we just claimed (reserved_at IS NOT NULL for our session)
 *   3. DELETE it.
 *
 * If the UPDATE affects 0 rows, another worker won the race — return null.
 *
 * IMPORTANT: the `jobs` table must have a `reserved_at` column (nullable TIMESTAMP).
 * Migration example:
 *   ALTER TABLE jobs ADD COLUMN reserved_at INTEGER NULL DEFAULT NULL;
 * (Unix timestamp integer is used for SQLite compatibility.)
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

    /**
     * FIX minor-3: atomic pop via reserved_at claim.
     *
     * Flow:
     *   a) Find the smallest available, unclaimed job id.
     *   b) Atomically mark it reserved (UPDATE WHERE reserved_at IS NULL).
     *      rowCount() === 0 means another worker claimed it first — bail.
     *   c) Fetch the full row, delete it, deserialize and return.
     *
     * A stale reserved job (worker crashed before DELETE) can be recovered
     * by a maintenance command that NULLifies reserved_at where
     * reserved_at < NOW() - grace_period.
     */
    public function pop(?string $queue = null): ?object
    {
        $pdo   = $this->connection();
        $queue = $queue ?? $this->config['queue'] ?? 'default';
        $now   = time();

        // Step 1: find the first available unclaimed job id.
        $find = $pdo->prepare(
            'SELECT id FROM jobs
             WHERE queue = :queue
               AND available_at <= :now
               AND reserved_at IS NULL
             ORDER BY id ASC
             LIMIT 1'
        );
        $find->execute([':queue' => $queue, ':now' => $now]);
        $id = $find->fetchColumn();

        if ($id === false) {
            return null;
        }

        // Step 2: atomically claim it (guard: reserved_at must still be NULL).
        $claim = $pdo->prepare(
            'UPDATE jobs
             SET reserved_at = :reserved_at, attempts = attempts + 1
             WHERE id = :id
               AND reserved_at IS NULL'
        );
        $claim->execute([':reserved_at' => $now, ':id' => $id]);

        if ($claim->rowCount() === 0) {
            // Another worker claimed it between our SELECT and UPDATE.
            return null;
        }

        // Step 3: fetch the claimed row, delete it, deserialize.
        $fetch = $pdo->prepare('SELECT * FROM jobs WHERE id = :id');
        $fetch->execute([':id' => $id]);
        $row = $fetch->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            // Should not happen, but guard anyway.
            return null;
        }

        $del = $pdo->prepare('DELETE FROM jobs WHERE id = :id');
        $del->execute([':id' => $id]);

        return $this->deserializeJob($row['payload']);
    }

    public function size(?string $queue = null): int
    {
        $pdo   = $this->connection();
        $queue = $queue ?? $this->config['queue'] ?? 'default';
        $stmt  = $pdo->prepare(
            'SELECT COUNT(*) FROM jobs WHERE queue = :queue AND available_at <= :now AND reserved_at IS NULL'
        );
        $stmt->execute([':queue' => $queue, ':now' => time()]);
        return (int) $stmt->fetchColumn();
    }

    protected function insertJob(object $job, string $queue, int $availableAt): int|string
    {
        $pdo  = $this->connection();
        $stmt = $pdo->prepare(
            'INSERT INTO jobs (queue, payload, attempts, available_at, reserved_at, created_at)
             VALUES (:queue, :payload, 0, :available_at, NULL, :created_at)'
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
