<?php

namespace Kyqo\Database\Orm;

use Kyqo\Database\QueryBuilder;

/**
 * ModelQueryBuilder
 *
 * Wraps the base QueryBuilder and hydrates results as Model instances.
 * Supports eager loading via with(), withCount(), has() and whereHas().
 */
class ModelQueryBuilder
{
    protected array $eagerLoad  = [];
    protected array $countLoad  = [];

    public function __construct(
        protected QueryBuilder $query,
        protected string       $modelClass
    ) {
        $this->query->setModel($modelClass);
    }

    // ── Eager load ──────────────────────────────────────────────────────────

    public function with(array|string $relations): static
    {
        $this->eagerLoad = array_merge(
            $this->eagerLoad,
            is_string($relations) ? [$relations] : $relations
        );
        return $this;
    }

    /**
     * Add a sub-select count for a relation.
     *
     * Usage:  Post::withCount('comments')->get();
     * Result: each model gets a `comments_count` attribute.
     */
    public function withCount(array|string $relations): static
    {
        foreach ((array) $relations as $relation) {
            $this->countLoad[] = $relation;
        }
        return $this;
    }

    /**
     * Constrain the query to models that have at least $count related records.
     *
     * Usage:  Post::has('comments')->get();
     *         Post::has('comments', '>=', 3)->get();
     */
    public function has(string $relation, string $operator = '>=', int $count = 1): static
    {
        return $this->whereHas($relation, null, $operator, $count);
    }

    /**
     * Constrain with a callback applied on the relation query.
     *
     * Usage:  Post::whereHas('comments', fn($q) => $q->where('approved', 1))->get();
     */
    public function whereHas(string $relation, ?\Closure $callback = null, string $operator = '>=', int $count = 1): static
    {
        $instance  = new $this->modelClass();
        $rel       = $instance->{$relation}();
        $relQuery  = $rel->getQuery()->query; // underlying QueryBuilder

        if ($callback !== null) {
            $mqb = new static($relQuery, get_class($rel->getModel()));
            $callback($mqb);
            $relQuery = $mqb->query;
        }

        // Build EXISTS sub-query string
        $subSql = $relQuery->toSql();

        $this->query->whereRaw(
            "(SELECT COUNT(*) FROM ({$subSql}) AS _has_sub) {$operator} {$count}",
            $relQuery->getBindings()
        );

        return $this;
    }

    // ── Fetch ────────────────────────────────────────────────────────────────

    public function get(): array
    {
        $rows   = $this->query->get();
        $models = array_map(fn ($row) => $this->hydrate((array) $row), $rows);
        $models = $this->eagerLoadRelations($models);
        return $this->loadCounts($models);
    }

    public function first(): ?Model
    {
        $row = $this->query->first();
        if ($row === null) return null;
        $model  = $this->hydrate((array) $row);
        $models = $this->eagerLoadRelations([$model]);
        $models = $this->loadCounts($models);
        return $models[0] ?? null;
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $page    = max(1, $page);
        $total   = $this->query->count();
        $rows    = $this->query->offset(($page - 1) * $perPage)->limit($perPage)->get();
        $models  = array_map(fn ($row) => $this->hydrate((array) $row), $rows);
        $models  = $this->eagerLoadRelations($models);
        $models  = $this->loadCounts($models);
        return [
            'data'         => $models,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    // ── Hydration ────────────────────────────────────────────────────────────

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

    protected function loadCounts(array $models): array
    {
        if (empty($models) || empty($this->countLoad)) return $models;

        foreach ($this->countLoad as $relation) {
            $instance = new $this->modelClass();
            if (!method_exists($instance, $relation)) continue;

            foreach ($models as $model) {
                $rel   = $model->{$relation}();
                $count = $rel->getQuery()->query->count();
                $model->attributes[$relation . '_count'] = $count;
            }
        }

        return $models;
    }

    // ── Misc ─────────────────────────────────────────────────────────────────

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
