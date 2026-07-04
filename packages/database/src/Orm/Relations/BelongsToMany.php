<?php

namespace Kyqo\Database\Orm\Relations;

use Kyqo\Database\Orm\Model;
use Kyqo\Database\QueryBuilder;

/**
 * BelongsToMany (many-to-many) relation.
 *
 * Usage:
 *   public function roles(): BelongsToMany
 *   {
 *       return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
 *   }
 */
class BelongsToMany extends Relation
{
    public function __construct(
        QueryBuilder $query,
        Model        $parent,
        protected string $table,        // pivot table
        string       $foreignKey,       // parent FK in pivot  (e.g. user_id)
        string       $localKey,         // related FK in pivot (e.g. role_id)
        protected string $parentKey = 'id',
        protected string $relatedKey = 'id'
    ) {
        parent::__construct($query, $parent, $foreignKey, $localKey);
    }

    public function getResults(): array
    {
        return $this->buildJoinQuery()->get();
    }

    public function addEagerConstraints(array $models): void
    {
        $this->buildJoinQuery()->whereIn(
            $this->table . '.' . $this->foreignKey,
            $this->getKeys($models, $this->parentKey)
        );
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $pivotKey = $result->{'pivot_' . $this->foreignKey} ?? $result->{$this->foreignKey};
            $dictionary[$pivotKey][] = $result;
        }
        foreach ($models as $model) {
            $key = $model->{$this->parentKey};
            $model->setRelation($relation, $dictionary[$key] ?? []);
        }
        return $models;
    }

    /** Attach related models via the pivot table. */
    public function attach(int|array $ids, array $attributes = []): void
    {
        $connection = $this->query->getConnection();
        foreach ((array) $ids as $id) {
            $row = array_merge([
                $this->foreignKey => $this->getParentKey(),
                $this->localKey   => $id,
            ], $attributes);
            $cols   = implode(', ', array_keys($row));
            $places = implode(', ', array_fill(0, count($row), '?'));
            $connection->statement(
                "INSERT IGNORE INTO {$this->table} ({$cols}) VALUES ({$places})",
                array_values($row)
            );
        }
    }

    /** Detach related models from the pivot table. */
    public function detach(int|array|null $ids = null): void
    {
        $connection = $this->query->getConnection();
        if ($ids === null) {
            $connection->statement(
                "DELETE FROM {$this->table} WHERE {$this->foreignKey} = ?",
                [$this->getParentKey()]
            );
        } else {
            $placeholders = implode(', ', array_fill(0, count((array) $ids), '?'));
            $connection->statement(
                "DELETE FROM {$this->table} WHERE {$this->foreignKey} = ? AND {$this->localKey} IN ({$placeholders})",
                array_merge([$this->getParentKey()], (array) $ids)
            );
        }
    }

    /** Sync: detach all then attach given ids. */
    public function sync(array $ids): void
    {
        $this->detach();
        $this->attach($ids);
    }

    protected function buildJoinQuery(): QueryBuilder
    {
        return $this->query
            ->join(
                $this->table,
                $this->query->getModel()->getTable() . '.' . $this->relatedKey,
                '=',
                $this->table . '.' . $this->localKey
            )
            ->where(
                $this->table . '.' . $this->foreignKey,
                '=',
                $this->getParentKey()
            );
    }
}
