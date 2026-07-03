<?php

namespace Kyqo\Database\Schema;

use Kyqo\Database\Connection;

/**
 * Schema builder — create, modify, and drop tables.
 *
 * Usage:
 *   Schema::create('users', function (Blueprint $t) {
 *       $t->id();
 *       $t->string('email')->unique();
 *       $t->timestamps();
 *   });
 *
 *   Schema::drop('users');
 *   Schema::hasTable('users');  // bool
 */
class SchemaBuilder
{
    public function __construct(protected Connection $connection) {}

    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, true);
        $callback($blueprint);
        $this->connection->statement($this->compileCreate($blueprint));

        foreach ($blueprint->foreign as $fk) {
            // Foreign keys are compiled inline for create; nothing extra needed.
        }
    }

    public function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, false);
        $callback($blueprint);
        foreach ($this->compileAlter($blueprint) as $sql) {
            $this->connection->statement($sql);
        }
    }

    public function drop(string $table): void
    {
        $this->connection->statement('DROP TABLE `' . $table . '`');
    }

    public function dropIfExists(string $table): void
    {
        $this->connection->statement('DROP TABLE IF EXISTS `' . $table . '`');
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
            $rows = $this->connection->select("PRAGMA table_info(`{$table}`)");
            return in_array($column, array_column($rows, 'name'), true);
        }
        $row = $this->connection->selectOne(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',
            [$this->connection->getDatabase(), $table, $column]
        );
        return $row !== null;
    }

    // ---- SQL compilation ----------------------------------------------------

    protected function compileCreate(Blueprint $bp): string
    {
        $parts = [];
        foreach ($bp->columns as $col) {
            $parts[] = $col->toSql();
        }
        foreach ($bp->indexes as $idx) {
            $parts[] = $idx;
        }
        foreach ($bp->foreign as $fk) {
            $parts[] = $fk->toSql();
        }
        return 'CREATE TABLE `' . $bp->table . '` (' . "\n  " . implode(",\n  ", $parts) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    protected function compileAlter(Blueprint $bp): array
    {
        $sqls = [];
        foreach ($bp->columns as $col) {
            $sqls[] = 'ALTER TABLE `' . $bp->table . '` ADD COLUMN ' . $col->toSql();
        }
        foreach ($bp->indexes as $idx) {
            $sqls[] = 'ALTER TABLE `' . $bp->table . '` ADD ' . $idx;
        }
        foreach ($bp->foreign as $fk) {
            $sqls[] = 'ALTER TABLE `' . $bp->table . '` ADD ' . $fk->toSql();
        }
        return $sqls;
    }
}
