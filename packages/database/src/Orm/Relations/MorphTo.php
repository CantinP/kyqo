<?php

namespace Kyqo\Database\Orm\Relations;

use Kyqo\Database\Orm\Model;
use Kyqo\Database\QueryBuilder;

/**
 * MorphTo — inverse polymorphic relation.
 *
 * The child model stores both the parent's ID and its class name.
 *
 * Schema on the child table:
 *   `commentable_id`   BIGINT
 *   `commentable_type` VARCHAR
 *
 * Usage on Comment model:
 *   public function commentable(): MorphTo
 *   {
 *       return $this->morphTo();
 *   }
 *
 * Then:  $comment->commentable  → returns the parent Post or Video
 */
class MorphTo extends Relation
{
    public function __construct(
        QueryBuilder   $query,
        Model          $parent,
        private string $morphType,   // e.g. 'commentable_type'
        private string $morphId      // e.g. 'commentable_id'
    ) {
        parent::__construct($query, $parent, $morphId, $parent->getPrimaryKey());
    }

    public function getResults(): mixed
    {
        $type  = $this->parent->getAttribute($this->morphType);
        $id    = $this->parent->getAttribute($this->morphId);

        if (!$type || !$id) return null;

        if (!class_exists($type)) return null;

        return $type::find($id);
    }

    public function addEagerConstraints(array $models): void
    {
        // Grouped by morph type — lazy load per model for simplicity
    }

    public function match(array $models, array $results, string $relation): array
    {
        foreach ($models as $model) {
            $result = $this->getResultForModel($model);
            $model->setRelation($relation, $result);
        }
        return $models;
    }

    private function getResultForModel(Model $model): mixed
    {
        $type = $model->getAttribute($this->morphType);
        $id   = $model->getAttribute($this->morphId);
        if (!$type || !$id || !class_exists($type)) return null;
        return $type::find($id);
    }
}
