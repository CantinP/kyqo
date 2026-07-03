<?php

namespace Kyqo\Database;

use PDO;
use PDOStatement;

/**
 * Wraps a PDO connection with query helpers.
 *
 * All user-supplied values go through prepared statements — never interpolated.
 */
class Connection
{
    protected PDO    $pdo;
    protected string $driver;
    protected string $database;
    protected int    $queryCount = 0;

    public function __construct(PDO $pdo, string $driver = 'mysql', string $database = '')
    {
        $this->pdo      = $pdo;
        $this->driver   = $driver;
        $this->database = $database;
    }

    // ---- Raw query helpers --------------------------------------------------

    public function select(string $sql, array $bindings = []): array
    {
        $stmt = $this->run($sql, $bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $stmt = $this->run($sql, $bindings);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function insert(string $sql, array $bindings = []): bool
    {
        $this->run($sql, $bindings);
        return true;
    }

    public function update(string $sql, array $bindings = []): int
    {
        $stmt = $this->run($sql, $bindings);
        return $stmt->rowCount();
    }

    public function delete(string $sql, array $bindings = []): int
    {
        $stmt = $this->run($sql, $bindings);
        return $stmt->rowCount();
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        $this->run($sql, $bindings);
        return true;
    }

    public function lastInsertId(): string|false
    {
        return $this->pdo->lastInsertId();
    }

    // ---- Transactions -------------------------------------------------------

    public function beginTransaction(): bool  { return $this->pdo->beginTransaction(); }
    public function commit(): bool            { return $this->pdo->commit(); }
    public function rollback(): bool          { return $this->pdo->rollBack(); }

    public function transaction(callable $callback): mixed
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

    // ---- QueryBuilder factory -----------------------------------------------

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    // ---- Schema factory -----------------------------------------------------

    public function schema(): Schema\SchemaBuilder
    {
        return new Schema\SchemaBuilder($this);
    }

    // ---- Accessors ----------------------------------------------------------

    public function getPdo(): PDO      { return $this->pdo; }
    public function getDriver(): string  { return $this->driver; }
    public function getDatabase(): string { return $this->database; }
    public function queryCount(): int    { return $this->queryCount; }

    // ---- Internals ----------------------------------------------------------

    protected function run(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        $this->queryCount++;
        return $stmt;
    }
}
