<?php

namespace Kyqo\Database\Schema;

/**
 * Fluent column definition used by Blueprint.
 *
 * FIX #8: toSql() now accepts a $driver parameter so SQLite/PostgreSQL
 * can receive double-quoted identifiers instead of backticks.
 * AUTO_INCREMENT becomes AUTOINCREMENT for SQLite,
 * SERIAL/BIGSERIAL for PostgreSQL.
 */
class ColumnDefinition
{
    public string  $name;
    public string  $type;
    public bool    $nullable    = false;
    public bool    $isPrimary   = false;
    public bool    $isUnique    = false;
    public mixed   $default     = null;
    public bool    $hasDefault  = false;
    public ?string $comment     = null;
    public bool    $unsigned    = false;

    public function __construct(string $name, string $type)
    {
        $this->name = $name;
        $this->type = $type;
    }

    public function nullable(bool $value = true): static
    {
        $this->nullable = $value;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default    = $value;
        $this->hasDefault = true;
        return $this;
    }

    public function primary(): static
    {
        $this->isPrimary = true;
        return $this;
    }

    public function unique(): static
    {
        $this->isUnique = true;
        return $this;
    }

    public function comment(string $text): static
    {
        $this->comment = $text;
        return $this;
    }

    public function unsigned(): static
    {
        $this->unsigned = true;
        return $this;
    }

    /**
     * FIX #8: driver-aware SQL generation.
     */
    public function toSql(string $driver = 'mysql'): string
    {
        $q    = $driver === 'mysql' ? '`' : '"';
        $type = $this->adaptType($this->type, $driver);
        $col  = $q . $this->name . $q . ' ' . $type;

        $col .= $this->nullable ? ' NULL' : ' NOT NULL';

        if ($this->hasDefault) {
            $default = $this->default;
            if ($default === null) {
                $col .= ' DEFAULT NULL';
            } elseif (is_bool($default)) {
                $col .= ' DEFAULT ' . ($default ? '1' : '0');
            } elseif (is_int($default) || is_float($default)) {
                $col .= ' DEFAULT ' . $default;
            } else {
                $col .= " DEFAULT '" . str_replace("'", "''", (string) $default) . "'";
            }
        }

        if ($this->isPrimary) {
            $col .= ' PRIMARY KEY';
        }

        if ($this->isUnique) {
            $col .= ' UNIQUE';
        }

        if ($this->comment !== null && $driver === 'mysql') {
            $col .= " COMMENT '" . str_replace("'", "''", $this->comment) . "'";
        }

        return $col;
    }

    /**
     * Translate MySQL types to driver-native equivalents.
     */
    protected function adaptType(string $type, string $driver): string
    {
        if ($driver === 'mysql') {
            return $type;
        }

        $map = [
            'BIGINT UNSIGNED AUTO_INCREMENT' => $driver === 'pgsql' ? 'BIGSERIAL' : 'INTEGER',
            'INT UNSIGNED AUTO_INCREMENT'    => $driver === 'pgsql' ? 'SERIAL'    : 'INTEGER',
            'BIGINT UNSIGNED'                => $driver === 'pgsql' ? 'BIGINT'    : 'INTEGER',
            'INT UNSIGNED'                   => 'INTEGER',
            'TINYINT(1)'                     => $driver === 'pgsql' ? 'BOOLEAN'   : 'INTEGER',
            'TINYINT'                        => 'SMALLINT',
            'DATETIME'                       => $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME',
            'LONGTEXT'                       => $driver === 'pgsql' ? 'TEXT'      : 'TEXT',
        ];

        return $map[$type] ?? $type;
    }
}
