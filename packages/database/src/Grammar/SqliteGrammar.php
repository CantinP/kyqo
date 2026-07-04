<?php

namespace Kyqo\Database\Grammar;

/**
 * SQLite-specific SQL grammar.
 *
 * Overrides the parts of the base grammar that differ on SQLite:
 * - No UNSIGNED / AUTO_INCREMENT → INTEGER PRIMARY KEY AUTOINCREMENT
 * - LIMIT/OFFSET use standard syntax (already ok in base)
 * - No FOR UPDATE (skip silently)
 * - ILIKE → LIKE (SQLite LIKE is case-insensitive for ASCII)
 * - datetime() instead of NOW()
 */
class SqliteGrammar extends Grammar
{
    public function compileColumnDefinition(array $column): string
    {
        $type = match ($column['type']) {
            'integer', 'bigInteger', 'smallInteger', 'tinyInteger' => 'INTEGER',
            'string', 'char', 'text', 'mediumText', 'longText'     => 'TEXT',
            'float', 'double', 'decimal'                           => 'REAL',
            'boolean'                                               => 'INTEGER',
            'date', 'dateTime', 'timestamp'                        => 'TEXT',
            'json', 'jsonb'                                         => 'TEXT',
            'binary', 'blob'                                        => 'BLOB',
            default                                                 => 'TEXT',
        };

        $sql = $type;

        if (!empty($column['autoIncrement'])) {
            $sql .= ' PRIMARY KEY AUTOINCREMENT';
            return $sql; // No NOT NULL / DEFAULT needed
        }

        if (!empty($column['nullable'])) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }

        if (array_key_exists('default', $column)) {
            $default = $column['default'];
            $sql .= ' DEFAULT ' . (is_string($default) ? "'" . addslashes($default) . "'" : (string) $default);
        }

        return $sql;
    }

    public function compileCreateTable(string $table, array $columns, array $indexes = []): string
    {
        $cols = [];
        foreach ($columns as $name => $column) {
            $def = $this->compileColumnDefinition($column);
            $cols[] = "    `{$name}` {$def}";
        }

        foreach ($indexes as $index) {
            if ($index['type'] === 'primary' && !isset($index['autoIncrement'])) {
                $keys   = implode('`, `', $index['columns']);
                $cols[] = "    PRIMARY KEY (`{$keys}`)";
            } elseif ($index['type'] === 'unique') {
                $keys   = implode('`, `', $index['columns']);
                $cols[] = "    UNIQUE (`{$keys}`)";
            }
        }

        return 'CREATE TABLE IF NOT EXISTS `' . $table . "` (\n" . implode(",\n", $cols) . "\n)";
    }

    public function compileDateTimeNow(): string
    {
        return "datetime('now')";
    }

    /** SQLite: LIKE is case-insensitive for ASCII; just alias ILIKE to LIKE. */
    public function compileOperator(string $operator): string
    {
        if (strtoupper($operator) === 'ILIKE') return 'LIKE';
        return $operator;
    }

    /** SQLite has no FOR UPDATE. */
    public function compileLockForUpdate(): string
    {
        return '';
    }
}
