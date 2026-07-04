<?php

namespace Kyqo\Database;

use Kyqo\Database\Grammar\Grammar;
use Kyqo\Database\Grammar\SqliteGrammar;

/**
 * Wraps a PDO connection and provides the query builder entry point.
 *
 * Driver detection for SQLite:
 *   - Passes SqliteGrammar to the QueryBuilder so that SQLite-specific
 *     SQL differences (AUTOINCREMENT, datetime(), no FOR UPDATE, etc.)
 *     are handled transparently.
 */
class Connection
{
    private Grammar $grammar;

    public function __construct(
        private \PDO   $pdo,
        private string $driver = 'mysql',
        private string $tablePrefix = ''
    ) {
        $this->grammar = $this->makeGrammar($driver);
    }

    private function makeGrammar(string $driver): Grammar
    {
        return match (strtolower($driver)) {
            'sqlite' => new SqliteGrammar(),
            default  => new Grammar(),
        };
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this->pdo, $this->tablePrefix . $table, $this->grammar);
    }

    public function getPdo(): \PDO { return $this->pdo; }
    public function getDriver(): string { return $this->driver; }
    public function getGrammar(): Grammar { return $this->grammar; }

    public function beginTransaction(): void  { $this->pdo->beginTransaction(); }
    public function commit(): void            { $this->pdo->commit(); }
    public function rollback(): void          { $this->pdo->rollBack(); }

    public function transaction(\Closure $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($bindings);
    }

    public function select(string $sql, array $bindings = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    public function scalar(string $sql, array $bindings = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchColumn();
    }

    /** Return the last inserted ID. */
    public function lastInsertId(): string|int
    {
        return $this->pdo->lastInsertId();
    }
}
