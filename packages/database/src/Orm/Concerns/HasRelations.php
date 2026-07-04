<?php

namespace Kyqo\Database\Orm\Concerns;

use Kyqo\Database\Orm\Relations\BelongsTo;
use Kyqo\Database\Orm\Relations\BelongsToMany;
use Kyqo\Database\Orm\Relations\HasMany;
use Kyqo\Database\Orm\Relations\HasOne;
use Kyqo\Database\Orm\Relations\MorphMany;
use Kyqo\Database\Orm\Relations\MorphOne;
use Kyqo\Database\Orm\Relations\MorphTo;

trait HasRelations
{
    protected array $relations = [];

    // ── Standard relations ───────────────────────────────────────────────────

    public function hasOne(string $related, string $foreignKey = null, string $localKey = null): HasOne
    {
        $instance   = new $related();
        $foreignKey ??= $this->getForeignKeyName();
        $localKey   ??= $this->getPrimaryKey();
        $query      = $instance->newQuery()->getQuery();
        return new HasOne($query, $this, $foreignKey, $localKey);
    }

    public function hasMany(string $related, string $foreignKey = null, string $localKey = null): HasMany
    {
        $instance   = new $related();
        $foreignKey ??= $this->getForeignKeyName();
        $localKey   ??= $this->getPrimaryKey();
        $query      = $instance->newQuery()->getQuery();
        return new HasMany($query, $this, $foreignKey, $localKey);
    }

    public function belongsTo(string $related, string $foreignKey = null, string $ownerKey = null): BelongsTo
    {
        $instance   = new $related();
        $foreignKey ??= $instance->getForeignKeyName();
        $ownerKey   ??= $instance->getPrimaryKey();
        $query      = $instance->newQuery()->getQuery();
        return new BelongsTo($query, $this, $foreignKey, $ownerKey);
    }

    public function belongsToMany(
        string $related,
        string $pivotTable  = null,
        string $foreignKey  = null,
        string $relatedKey  = null
    ): BelongsToMany {
        $instance   = new $related();
        $foreignKey ??= $this->getForeignKeyName();
        $relatedKey ??= $instance->getForeignKeyName();
        $pivotTable ??= $this->guessPivotTable($related);
        $query      = $instance->newQuery()->getQuery();
        return new BelongsToMany($query, $this, $pivotTable, $foreignKey, $relatedKey, $instance->getPrimaryKey());
    }

    // ── Polymorphic relations ────────────────────────────────────────────

    /**
     * Define a polymorphic inverse relation (belongs to a morphable parent).
     *
     * @param  string|null $name   The morph name (e.g. 'commentable').
     *                             Defaults to the calling method name.
     */
    public function morphTo(string $name = null): MorphTo
    {
        // Guess morph name from the calling method if not provided
        if ($name === null) {
            $trace  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $name   = $trace[1]['function'] ?? 'morphable';
        }

        $morphType  = $name . '_type';
        $morphId    = $name . '_id';
        $connection = static::getResolver()->connection();
        $query      = $connection->table($this->getTable());

        return new MorphTo($query, $this, $morphType, $morphId);
    }

    /**
     * A model has one related record of any type via a polymorphic relation.
     *
     * @param  string $related    Fully qualified related model class.
     * @param  string $name       Morph name (e.g. 'imageable').
     * @param  string|null $localKey
     */
    public function morphOne(string $related, string $name, string $localKey = null): MorphOne
    {
        $instance = new $related();
        $localKey ??= $this->getPrimaryKey();
        $query    = $instance->newQuery()->getQuery();
        return new MorphOne($query, $this, $related, $name, $localKey);
    }

    /**
     * A model has many related records of any type via a polymorphic relation.
     */
    public function morphMany(string $related, string $name, string $localKey = null): MorphMany
    {
        $instance = new $related();
        $localKey ??= $this->getPrimaryKey();
        $query    = $instance->newQuery()->getQuery();
        return new MorphMany($query, $this, $related, $name, $localKey);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    public function setRelation(string $key, mixed $value): static
    {
        $this->relations[$key] = $value;
        return $this;
    }

    public function getRelation(string $key): mixed
    {
        return $this->relations[$key] ?? null;
    }

    protected function getForeignKeyName(): string
    {
        return strtolower(class_basename(static::class)) . '_id';
    }

    protected function guessPivotTable(string $related): string
    {
        $models = [
            strtolower(class_basename(static::class)),
            strtolower(class_basename($related)),
        ];
        sort($models);
        return implode('_', $models);
    }
}
