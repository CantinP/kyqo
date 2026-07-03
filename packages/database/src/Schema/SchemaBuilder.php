<?php

namespace Kyqo\Database\Schema;

use Kyqo\Database\Connection;

/**
 * Schema builder — create, modify, and drop tables.
 *
 * FIX #3: drop() and dropIfExists() now use quoteIdentifier() to prevent
 *         SQL injection via table name interpolation.
 * FIX #8: compileCreate() is now driver-aware (MySQL backtick/InnoDB vs
 *         SQLite/PostgreSQL double-quote, no ENGINE clause).
 */
class SchemaBuilder
{
    public function __construct(protected Connection $connection) {}

    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, true);
        $callback($blueprint);
        $this->connection->statement($this->compileCreate($blueprint));
    }

    public function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, false);
        $callback($blueprint);
        foreach ($this->compileAlter($blueprint) as $sql) {
            $this->connection->statement($sql);
        }
    }

    /**
     * FIX #3: use quoteIdentifier() — never raw interpolation.
     */
    public function drop(string $table): void
    {
        $this->connection->statement('DROP TABLE ' . $this->quoteIdentifier($table));
    }

    public function dropIfExists(string $table): void
    {
        $this->connection->statement('DROP TABLE IF EXISTS ' . $this->quoteIdentifier($table));
    }

    public function hasTable(string $table): bool
    {
        $driver = $this->connection->getDriver();
        if ($driver === 'sqlite') {
            $row = $this->connection->selectOne(
                "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
                [$table]
            );
        } else {
            $row = $this->connection->selectOne(
                'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?',
                [$this->connection->getDatabase(), $table]
            );
        }
        return $row !== null;
    }

    public function hasColumn(string $table, string $column): bool
    {
        $driver = $this->connection->getDriver();
        if ($driver === 'sqlite') {
            $rows = $this->connection->select("PRAGMA table_info(" . $this->quoteIdentifier($table) . ")");
            return in_array($column, array_column($rows, 'name'), true);
        }
        $row = $this->connection->selectOne(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',
            [$this->connection->getDatabase(), $table, $column]
        );
        return $row !== null;
    }

    // ---- SQL compilation ----------------------------------------------------

    /**
     * FIX #8: driver-aware compilation.
     * - MySQL/MariaDB : backtick quotes + ENGINE=InnoDB
     * - SQLite        : double-quote identifiers, no ENGINE
     * - PostgreSQL    : double-quote identifiers, no ENGINE
     */
    protected function compileCreate(Blueprint $bp): string
    {
        $driver = $this->connection->getDriver();
        $q      = $driver === 'mysql' ? '`' : '"';

        $parts = [];
        foreach ($bp->columns as $col) {
            $parts[] = $col->toSql($driver);
        }
        foreach ($bp->indexes as $idx) {
            // Skip MySQL-specific index syntax for non-MySQL drivers
            if ($driver !== 'mysql' && str_starts_with($idx, 'KEY ')) {
                continue;
            }
            $parts[] = $idx;
        }
        foreach ($bp->foreign as $fk) {
            $parts[] = $fk->toSql($driver);
        }

        $sql = 'CREATE TABLE ' . $q . $bp->table . $q
             . ' (' . "\n  " . implode(",\n  ", $parts) . "\n)";

        if ($driver === 'mysql') {
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }

        return $sql;
    }

    protected function compileAlter(Blueprint $bp): array
    {
        $driver = $this->connection->getDriver();
        $q      = $driver === 'mysql' ? '`' : '"';
        $sqls   = [];

        foreach ($bp->columns as $col) {
            $sqls[] = 'ALTER TABLE ' . $q . $bp->table . $q . ' ADD COLUMN ' . $col->toSql($driver);
        }
        foreach ($bp->indexes as $idx) {
            $sqls[] = 'ALTER TABLE ' . $q . $bp->table . $q . ' ADD ' . $idx;
        }
        foreach ($bp->foreign as $fk) {
            $sqls[] = 'ALTER TABLE ' . $q . $bp->table . $q . ' ADD ' . $fk->toSql($driver);
        }
        return $sqls;
    }

    /**
     * FIX #3: safe identifier quoting — validates then quotes.
     */
    protected function quoteIdentifier(string $name): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException("Invalid table name: [{$name}]");
        }
        $driver = $this->connection->getDriver();
        if ($driver === 'mysql') {
            return '`' . str_replace('`', '``', $name) . '`';
        }
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
