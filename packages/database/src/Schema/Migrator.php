<?php

namespace Kyqo\Database\Schema;

use Kyqo\Database\Connection;

/**
 * Runs and rolls back migrations.
 *
 * FIX #12: resolve() now uses a class-cache keyed by filename to avoid
 * returning PHP's plain `1` on subsequent requires of the same file.
 * Each anonymous class is instantiated once and cached.
 */
class Migrator
{
    /** @var array<string, Migration> */
    protected array $resolved = [];

    public function __construct(
        protected Connection $connection,
        protected string     $path
    ) {
        $this->ensureMigrationsTable();
    }

    // ---- Public API ---------------------------------------------------------

    public function run(): array
    {
        $pending = $this->pending();
        if (empty($pending)) {
            return [];
        }

        $batch = $this->nextBatchNumber();
        $ran   = [];

        foreach ($pending as $file) {
            $migration = $this->resolve($file);
            $migration->up($this->connection);
            $this->log($file, $batch);
            $ran[] = $file;
        }

        return $ran;
    }

    public function rollback(): array
    {
        $batch = $this->lastBatchNumber();
        if ($batch === 0) {
            return [];
        }

        $rows   = $this->connection->select(
            'SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC',
            [$batch]
        );
        $rolled = [];

        foreach ($rows as $row) {
            $file      = $row['migration'];
            $migration = $this->resolve($file);
            $migration->down($this->connection);
            $this->connection->delete(
                'DELETE FROM migrations WHERE migration = ?',
                [$file]
            );
            $rolled[] = $file;
        }

        return $rolled;
    }

    public function status(): array
    {
        $ran  = array_column(
            $this->connection->select('SELECT migration FROM migrations'),
            'migration'
        );
        $all  = $this->allFiles();
        $out  = [];
        foreach ($all as $file) {
            $out[] = [
                'migration' => $file,
                'ran'       => in_array($file, $ran, true),
            ];
        }
        return $out;
    }

    public function reset(): array
    {
        $rows   = $this->connection->select('SELECT migration FROM migrations ORDER BY id DESC');
        $rolled = [];
        foreach ($rows as $row) {
            $file      = $row['migration'];
            $migration = $this->resolve($file);
            $migration->down($this->connection);
            $this->connection->delete('DELETE FROM migrations WHERE migration = ?', [$file]);
            $rolled[] = $file;
        }
        return $rolled;
    }

    // ---- Internals ----------------------------------------------------------

    protected function pending(): array
    {
        $ran = array_column(
            $this->connection->select('SELECT migration FROM migrations'),
            'migration'
        );
        return array_values(array_diff($this->allFiles(), $ran));
    }

    protected function allFiles(): array
    {
        $files = glob($this->path . '/*.php');
        if (!$files) {
            return [];
        }
        sort($files);
        return array_map(fn ($f) => basename($f, '.php'), $files);
    }

    /**
     * FIX #12: cache resolved Migration instances so that the same file
     * is never `require`d twice (PHP would return `1` on the second call).
     */
    protected function resolve(string $name): Migration
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $file = $this->path . '/' . $name . '.php';
        if (!file_exists($file)) {
            throw new \RuntimeException("Migration file [{$file}] not found.");
        }

        $migration = require $file;

        if (!$migration instanceof Migration) {
            throw new \RuntimeException(
                "Migration file [{$file}] must return an instance of Migration."
            );
        }

        $this->resolved[$name] = $migration;
        return $migration;
    }

    protected function log(string $migration, int $batch): void
    {
        $this->connection->insert(
            'INSERT INTO migrations (migration, batch) VALUES (?, ?)',
            [$migration, $batch]
        );
    }

    protected function nextBatchNumber(): int
    {
        return $this->lastBatchNumber() + 1;
    }

    protected function lastBatchNumber(): int
    {
        $row = $this->connection->selectOne('SELECT MAX(batch) as max_batch FROM migrations');
        return (int) ($row['max_batch'] ?? 0);
    }

    protected function ensureMigrationsTable(): void
    {
        $driver = $this->connection->getDriver();
        if ($driver === 'sqlite') {
            $this->connection->statement(
                'CREATE TABLE IF NOT EXISTS migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, migration TEXT NOT NULL, batch INTEGER NOT NULL)'
            );
        } else {
            $this->connection->statement(
                'CREATE TABLE IF NOT EXISTS `migrations` (
                  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  `migration` VARCHAR(255) NOT NULL,
                  `batch` INT NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
    }
}
