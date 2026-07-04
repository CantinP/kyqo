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
        $query      = $instance->newQuery();
        return new HasOne($query, $this, $foreignKey, $localKey);
    }

    public function hasMany(string $related, string $foreignKey = '', string $localKey = 'id'): HasMany
    {
        $instance   = new $related();
        $foreignKey = $foreignKey ?: $this->guessForeignKey();
        $query      = $instance->newQuery();
        return new HasMany($query, $this, $foreignKey, $localKey);
    }

    public function belongsTo(string $related, string $foreignKey = '', string $ownerKey = 'id'): BelongsTo
    {
        $instance   = new $related();
        $foreignKey = $foreignKey ?: (strtolower(class_basename($related)) . '_id');
        $query      = $instance->newQuery();
        return new BelongsTo($query, $this, $foreignKey, $ownerKey);
    }

    public function belongsToMany(
        string $related,
        string $table       = '',
        string $foreignKey  = '',
        string $relatedKey  = '',
        string $parentKey   = 'id',
        string $relatedPKey = 'id'
    ): BelongsToMany {
        $instance   = new $related();
        $foreignKey = $foreignKey ?: $this->guessForeignKey();
        $relatedKey = $relatedKey ?: (strtolower(class_basename($related)) . '_id');
        $table      = $table      ?: $this->pivotTableName($related);
        $query      = $instance->newQuery();
        return new BelongsToMany($query, $this, $table, $foreignKey, $relatedKey, $parentKey, $relatedPKey);
    }

    public function setRelation(string $relation, mixed $value): static
    {
        $this->relations[$relation] = $value;
        return $this;
    }

    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation] ?? null;
    }

    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->relations);
    }

    public function getRelations(): array { return $this->relations; }

    /** with(['posts', 'roles']) — eager-load relations on a collection. */
    public static function with(array $relations): \Kyqo\Database\Orm\ModelQueryBuilder
    {
        return (new static())->newQuery()->with($relations);
    }

    protected function guessForeignKey(): string
    {
        return strtolower(class_basename(static::class)) . '_id';
    }

    protected function pivotTableName(string $related): string
    {
        $models = [
            strtolower(class_basename(static::class)),
            strtolower(class_basename($related)),
        ];
        sort($models);
        return implode('_', $models);
    }
}
