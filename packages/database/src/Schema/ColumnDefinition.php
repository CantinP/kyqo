<?php

namespace Kyqo\Database\Schema;

/**
 * Fluent column definition used by Blueprint.
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
    public ?int    $after       = null;

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
     * Generate the SQL fragment for this column.
     */
    public function toSql(): string
    {
        $sql = '`' . $this->name . '` ' . $this->type;

        if (!$this->nullable) {
            $sql .= ' NOT NULL';
        } else {
            $sql .= ' NULL';
        }

        if ($this->hasDefault) {
            $default = $this->default;
            if ($default === null) {
                $sql .= ' DEFAULT NULL';
            } elseif (is_bool($default)) {
                $sql .= ' DEFAULT ' . ($default ? '1' : '0');
            } elseif (is_int($default) || is_float($default)) {
                $sql .= ' DEFAULT ' . $default;
            } else {
                $sql .= " DEFAULT '" . str_replace("'", "''", (string) $default) . "'";
            }
        }

        if ($this->isPrimary) {
            $sql .= ' PRIMARY KEY';
        }

        if ($this->isUnique) {
            $sql .= ' UNIQUE';
        }

        if ($this->comment !== null) {
            $sql .= " COMMENT '" . str_replace("'", "''", $this->comment) . "'";
        }

        return $sql;
    }
}
