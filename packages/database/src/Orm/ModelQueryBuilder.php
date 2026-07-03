<?php

namespace Kyqo\Database\Orm;

use Kyqo\Database\Connection;
use Kyqo\Database\QueryBuilder;

/**
 * FIX #5: ORM-aware query builder that hydrates raw rows into Model instances.
 *
 * Returned by Model::query() / Model::where() so that get() and first()
 * return typed model instances instead of plain associative arrays.
 */
class ModelQueryBuilder extends QueryBuilder
{
    protected string $modelClass;

    public function __construct(Connection $connection, string $table, string $modelClass)
    {
        parent::__construct($connection, $table);
        $this->modelClass = $modelClass;
    }

    /**
     * Get all matching rows as hydrated model instances.
     *
     * @return Model[]
     */
    public function get(): array
    {
        $rows = parent::get();
        return array_map([$this->modelClass, 'hydratePublic'], $rows);
    }

    /**
     * Get the first matching row as a hydrated model instance.
     */
    public function first(): ?Model
    {
        $row = parent::first();
        return $row !== null ? ($this->modelClass)::hydratePublic($row) : null;
    }

    /**
     * find() already calls first() via parent, override for type safety.
     */
    public function find(mixed $id, string $primaryKey = 'id'): ?Model
    {
        return $this->where($primaryKey, $id)->first();
    }
}
