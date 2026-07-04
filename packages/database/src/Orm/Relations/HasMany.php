<?php

namespace Kyqo\Database\Orm\Relations;

use Kyqo\Database\Orm\Model;

/**
 * HasMany relation.
 *
 * Usage:
 *   public function posts(): HasMany
 *   {
 *       return $this->hasMany(Post::class, 'user_id', 'id');
 *   }
 */
class HasMany extends Relation
{
    public function getResults(): array
    {
        return $this->query
            ->where($this->foreignKey, '=', $this->getParentKey())
            ->get();
    }

    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn($this->foreignKey, $this->getKeys($models, $this->localKey));
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[$result->{$this->foreignKey}][] = $result;
        }
        foreach ($models as $model) {
            $key = $model->{$this->localKey};
            $model->setRelation($relation, $dictionary[$key] ?? []);
        }
        return $models;
    }
}
