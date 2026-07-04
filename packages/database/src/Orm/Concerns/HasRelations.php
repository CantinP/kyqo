<?php

namespace Kyqo\Database\Orm\Concerns;

use Kyqo\Database\Orm\Relations\BelongsTo;
use Kyqo\Database\Orm\Relations\BelongsToMany;
use Kyqo\Database\Orm\Relations\HasMany;
use Kyqo\Database\Orm\Relations\HasOne;

/**
 * Trait HasRelations
 *
 * Mixed into Model to provide relation factory methods and lazy / eager loading.
 */
trait HasRelations
{
    protected array $relations = [];

    public function hasOne(string $related, string $foreignKey = '', string $localKey = 'id'): HasOne
    {
        $instance   = new $related();
        $foreignKey = $foreignKey ?: $this->guessForeignKey();
        $query      = $instance->newQuery()->query;  // unwrap to raw QueryBuilder
        $query->setModel($related);
        return new HasOne($query, $this, $foreignKey, $localKey);
    }

    public function hasMany(string $related, string $foreignKey = '', string $localKey = 'id'): HasMany
    {
        $instance   = new $related();
        $foreignKey = $foreignKey ?: $this->guessForeignKey();
        $query      = $instance->newQuery()->query;
        $query->setModel($related);
        return new HasMany($query, $this, $foreignKey, $localKey);
    }

    public function belongsTo(string $related, string $foreignKey = '', string $ownerKey = 'id'): BelongsTo
    {
        $instance   = new $related();
        $foreignKey = $foreignKey ?: (strtolower(class_basename($related)) . '_id');
        $query      = $instance->newQuery()->query;
        $query->setModel($related);
        return new BelongsTo($query, $this, $foreignKey, $ownerKey);
    }

    public function belongsToMany(
        string $related,
        string $pivotTable  = '',
        string $foreignKey  = '',
        string $relatedKey  = '',
        string $parentKey   = 'id',
        string $relatedPKey = 'id'
    ): BelongsToMany {
        $instance   = new $related();
        $foreignKey = $foreignKey ?: $this->guessForeignKey();
        $relatedKey = $relatedKey ?: (strtolower(class_basename($related)) . '_id');

        if ($pivotTable === '') {
            // Convention: alphabetical order of the two table names joined by _
            $tables = [
                strtolower(class_basename(static::class)),
                strtolower(class_basename($related)),
            ];
            sort($tables);
            $pivotTable = implode('_', $tables);
        }

        $query = $instance->newQuery()->query;
        $query->setModel($related);

        return new BelongsToMany(
            $query, $this, $pivotTable,
            $foreignKey, $relatedKey,
            $parentKey, $relatedPKey
        );
    }

    // ── Relation registry ────────────────────────────────────────────────────

    public function setRelation(string $name, mixed $value): static
    {
        $this->relations[$name] = $value;
        return $this;
    }

    public function relationLoaded(string $name): bool
    {
        return array_key_exists($name, $this->relations);
    }

    public function getRelation(string $name): mixed
    {
        return $this->relations[$name] ?? null;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    protected function guessForeignKey(): string
    {
        return strtolower(class_basename(static::class)) . '_id';
    }
}
