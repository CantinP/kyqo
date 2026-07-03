<?php

namespace Kyqo\Database\Schema;

/**
 * Fluent foreign key definition.
 *
 * FIX #8: toSql() now accepts $driver to use correct quote char.
 */
class ForeignKeyDefinition
{
    protected string  $table;
    protected string  $column;
    protected ?string $refTable   = null;
    protected ?string $refColumn  = null;
    protected string  $onDelete   = 'RESTRICT';
    protected string  $onUpdate   = 'RESTRICT';

    public function __construct(string $table, string $column)
    {
        $this->table  = $table;
        $this->column = $column;
    }

    public function references(string $column): static
    {
        $this->refColumn = $column;
        return $this;
    }

    public function on(string $table): static
    {
        $this->refTable = $table;
        return $this;
    }

    public function onDelete(string $action): static
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): static
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    public function cascadeOnDelete(): static  { return $this->onDelete('CASCADE'); }
    public function nullOnDelete(): static     { return $this->onDelete('SET NULL'); }
    public function cascadeOnUpdate(): static  { return $this->onUpdate('CASCADE'); }

    /**
     * FIX #8: driver-aware quoting.
     */
    public function toSql(string $driver = 'mysql'): string
    {
        $q    = $driver === 'mysql' ? '`' : '"';
        $name = 'fk_' . $this->table . '_' . $this->column;

        return sprintf(
            'CONSTRAINT %s%s%s FOREIGN KEY (%s%s%s) REFERENCES %s%s%s (%s%s%s) ON DELETE %s ON UPDATE %s',
            $q, $name,       $q,
            $q, $this->column,  $q,
            $q, $this->refTable, $q,
            $q, $this->refColumn, $q,
            $this->onDelete,
            $this->onUpdate
        );
    }
}
