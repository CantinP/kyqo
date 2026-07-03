<?php

namespace Kyqo\Database\Schema;

/**
 * Fluent foreign key definition.
 *
 * Usage:
 *   $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
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

    public function toSql(): string
    {
        $name = 'fk_' . $this->table . '_' . $this->column;
        return sprintf(
            'CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s ON UPDATE %s',
            $name,
            $this->column,
            $this->refTable,
            $this->refColumn,
            $this->onDelete,
            $this->onUpdate
        );
    }
}
