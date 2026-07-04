<?php

namespace Kyqo\Database\Orm\Relations;

use Kyqo\Database\Orm\Model;
use Kyqo\Database\QueryBuilder;

/**
 * MorphOne — polymorphic HasOne.
 *
 * Usage on Post model:
 *   public function image(): MorphOne
 *   {
 *       return $this->morphOne(Image::class, 'imageable');
 *   }
 *
 *   $post->image → returns one Image
 */
class MorphOne extends Relation
{
    public function __construct(
        QueryBuilder   $query,
        Model          $parent,
        private string $related,
        private string $morphName,   // e.g. 'imageable'
        string         $localKey
    ) {
        parent::__construct(
            $query,
            $parent,
            $morphName . '_id',
            $localKey
        );
    }

    public function getResults(): ?Model
    {
        return $this->query
            ->where($this->morphName . '_type', '=', get_class($this->parent))
            ->where($this->morphName . '_id',   '=', $this->getParentKey())
            ->first();
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->localKey);
        $this->query
            ->where($this->morphName . '_type', '=', get_class($this->parent))
            ->whereIn($this->morphName . '_id', $keys);
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->{$this->morphName . '_id'} ?? $result->attributes[$this->morphName . '_id'] ?? null;
            if ($key !== null) $dictionary[$key] = $result;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $model->setRelation($relation, $dictionary[$key] ?? null);
        }

        return $models;
    }
}
