<?php

namespace Kyqo\Database\Orm\Relations;

use Kyqo\Database\Orm\Model;

/**
 * HasOne relation.
 *
 * Usage (inside a Model):
 *   public function phone(): HasOne
 *   {
 *       return $this->hasOne(Phone::class, 'user_id', 'id');
 *   }
 */
class HasOne extends Relation
{
    public function getResults(): ?Model
    {
        return $this->query
            ->where($this->foreignKey, '=', $this->getParentKey())
            ->first();
    }

    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn($this->foreignKey, $this->getKeys($models, $this->localKey));
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->{$this->foreignKey};
            $dictionary[$key] = $result;
        }
        foreach ($models as $model) {
            $key = $model->{$this->localKey};
            $model->setRelation($relation, $dictionary[$key] ?? null);
        }
        return $models;
    }
}
