<?php

namespace Kyqo\Database\Orm\Relations;

use Kyqo\Database\Orm\Model;

/**
 * BelongsTo relation.
 *
 * Usage:
 *   public function user(): BelongsTo
 *   {
 *       return $this->belongsTo(User::class, 'user_id', 'id');
 *   }
 */
class BelongsTo extends Relation
{
    public function getResults(): ?Model
    {
        return $this->query
            ->where($this->localKey, '=', $this->parent->{$this->foreignKey})
            ->first();
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = array_unique(array_filter(array_column(
            array_map(fn ($m) => (array) $m->getAttributes(), $models),
            $this->foreignKey
        )));
        $this->query->whereIn($this->localKey, array_values($keys));
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[$result->{$this->localKey}] = $result;
        }
        foreach ($models as $model) {
            $ownerKey = $model->{$this->foreignKey};
            $model->setRelation($relation, $dictionary[$ownerKey] ?? null);
        }
        return $models;
    }
}
