<?php

namespace Kyqo\Database\Orm;

use Kyqo\Database\QueryBuilder;

/**
 * ModelQueryBuilder
 *
 * Wraps the base QueryBuilder and hydrates results as Model instances.
 * Supports eager loading via with().
 */
class ModelQueryBuilder
{
    protected array $eagerLoad = [];

    public function __construct(
        protected QueryBuilder $query,
        protected string       $modelClass
    ) {}

    public function with(array|string $relations): static
    {
        $this->eagerLoad = array_merge(
            $this->eagerLoad,
            is_string($relations) ? [$relations] : $relations
        );
        return $this;
    }

    public function get(): array
    {
        $rows   = $this->query->get();
        $models = array_map(fn ($row) => $this->hydrate((array) $row), $rows);
        return $this->eagerLoadRelations($models);
    }

    public function first(): ?Model
    {
        $row = $this->query->first();
        if ($row === null) return null;
        $model  = $this->hydrate((array) $row);
        $models = $this->eagerLoadRelations([$model]);
        return $models[0] ?? null;
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $total   = $this->query->count();
        $rows    = $this->query->offset(($page - 1) * $perPage)->limit($perPage)->get();
        $models  = array_map(fn ($row) => $this->hydrate((array) $row), $rows);
        return [
            'data'          => $this->eagerLoadRelations($models),
            'total'         => $total,
            'per_page'      => $perPage,
            'current_page'  => $page,
            'last_page'     => (int) ceil($total / $perPage),
        ];
    }

    protected function hydrate(array $attributes): Model
    {
        $model = new $this->modelClass();
        $model->attributes = $attributes;
        $model->original   = $attributes;
        $model->exists     = true;
        return $model;
    }

    protected function eagerLoadRelations(array $models): array
    {
        if (empty($models) || empty($this->eagerLoad)) return $models;

        foreach ($this->eagerLoad as $name) {
            $instance = new $this->modelClass();
            if (!method_exists($instance, $name)) continue;

            $relation = $instance->{$name}();
            $relation->addEagerConstraints($models);
            $results  = $relation->getQuery()->get();
            $results  = array_map(fn ($r) => is_array($r) ? (object) $r : $r, $results);
            $models   = $relation->match($models, $results, $name);
        }

        return $models;
    }

    public function getModel(): Model { return new $this->modelClass(); }
    public function getConnection(): mixed { return $this->query->getConnection(); }

    public function __call(string $method, array $args): mixed
    {
        $result = $this->query->{$method}(...$args);
        if ($result instanceof QueryBuilder) {
            $this->query = $result;
            return $this;
        }
        return $result;
    }
}
