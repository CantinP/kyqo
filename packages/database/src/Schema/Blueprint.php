<?php

namespace Kyqo\Database\Schema;

/**
 * Defines the columns and indexes of a database table.
 */
class Blueprint
{
    public string $table;
    public array  $columns  = [];
    public array  $indexes  = [];
    public array  $foreign  = [];
    public bool   $create   = true;

    public function __construct(string $table, bool $create = true)
    {
        $this->table  = $table;
        $this->create = $create;
    }

    // ---- Primary / increments -----------------------------------------------

    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($name);
    }

    public function increments(string $name): ColumnDefinition
    {
        return $this->addColumn('INT UNSIGNED AUTO_INCREMENT', $name)->primary();
    }

    public function bigIncrements(string $name): ColumnDefinition
    {
        return $this->addColumn('BIGINT UNSIGNED AUTO_INCREMENT', $name)->primary();
    }

    // ---- Integer types ------------------------------------------------------

    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn('INT', $name);
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn('BIGINT', $name);
    }

    public function unsignedBigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn('BIGINT UNSIGNED', $name);
    }

    public function tinyInteger(string $name): ColumnDefinition
    {
        return $this->addColumn('TINYINT', $name);
    }

    // ---- String types -------------------------------------------------------

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn("VARCHAR({$length})", $name);
    }

    public function char(string $name, int $length = 100): ColumnDefinition
    {
        return $this->addColumn("CHAR({$length})", $name);
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn('TEXT', $name);
    }

    public function longText(string $name): ColumnDefinition
    {
        return $this->addColumn('LONGTEXT', $name);
    }

    // ---- Numeric types ------------------------------------------------------

    public function decimal(string $name, int $total = 8, int $places = 2): ColumnDefinition
    {
        return $this->addColumn("DECIMAL({$total},{$places})", $name);
    }

    public function float(string $name): ColumnDefinition
    {
        return $this->addColumn('FLOAT', $name);
    }

    public function double(string $name): ColumnDefinition
    {
        return $this->addColumn('DOUBLE', $name);
    }

    // ---- Boolean / enum -----------------------------------------------------

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn('TINYINT(1)', $name);
    }

    public function enum(string $name, array $values): ColumnDefinition
    {
        $list = implode(', ', array_map(fn($v) => "'" . str_replace("'", "''", $v) . "'", $values));
        return $this->addColumn("ENUM({$list})", $name);
    }

    // ---- Date / time types --------------------------------------------------

    public function date(string $name): ColumnDefinition
    {
        return $this->addColumn('DATE', $name);
    }

    public function dateTime(string $name, int $precision = 0): ColumnDefinition
    {
        $type = $precision > 0 ? "DATETIME({$precision})" : 'DATETIME';
        return $this->addColumn($type, $name);
    }

    public function timestamp(string $name, int $precision = 0): ColumnDefinition
    {
        $type = $precision > 0 ? "TIMESTAMP({$precision})" : 'TIMESTAMP';
        return $this->addColumn($type, $name);
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    public function softDeletes(): void
    {
        $this->timestamp('deleted_at')->nullable();
    }

    // ---- Special types ------------------------------------------------------

    public function json(string $name): ColumnDefinition
    {
        return $this->addColumn('JSON', $name);
    }

    public function uuid(string $name = 'id'): ColumnDefinition
    {
        return $this->addColumn('CHAR(36)', $name);
    }

    public function rememberToken(): ColumnDefinition
    {
        return $this->string('remember_token', 100)->nullable();
    }

    // ---- Indexes ------------------------------------------------------------

    public function primary(string|array $columns): void
    {
        $cols = is_array($columns) ? $columns : [$columns];
        $this->indexes[] = 'PRIMARY KEY (' . implode(', ', array_map(fn($c) => "`{$c}`", $cols)) . ')';
    }

    public function unique(string|array $columns, ?string $name = null): void
    {
        $cols  = is_array($columns) ? $columns : [$columns];
        $iname = $name ?? $this->table . '_' . implode('_', $cols) . '_unique';
        $this->indexes[] = "UNIQUE KEY `{$iname}` (" . implode(', ', array_map(fn($c) => "`{$c}`", $cols)) . ')';
    }

    public function index(string|array $columns, ?string $name = null): void
    {
        $cols  = is_array($columns) ? $columns : [$columns];
        $iname = $name ?? $this->table . '_' . implode('_', $cols) . '_index';
        $this->indexes[] = "KEY `{$iname}` (" . implode(', ', array_map(fn($c) => "`{$c}`", $cols)) . ')';
    }

    // ---- Foreign keys -------------------------------------------------------

    public function foreignId(string $name): ColumnDefinition
    {
        return $this->unsignedBigInteger($name);
    }

    public function foreign(string $column): ForeignKeyDefinition
    {
        $fk             = new ForeignKeyDefinition($this->table, $column);
        $this->foreign[] = $fk;
        return $fk;
    }

    // ---- Internals ----------------------------------------------------------

    protected function addColumn(string $type, string $name): ColumnDefinition
    {
        $col             = new ColumnDefinition($name, $type);
        $this->columns[] = $col;
        return $col;
    }
}
