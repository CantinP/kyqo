<?php

namespace Kyqo\Database\Orm\Relations;

use Kyqo\Database\Orm\Model;
use Kyqo\Database\QueryBuilder;

/**
 * Abstract base for all ORM relations.
 */
abstract class Relation
{
    public function __construct(
        protected QueryBuilder $query,
        protected Model        $parent,
        protected string       $foreignKey,
        protected string       $localKey
    ) {}

    abstract public function getResults(): mixed;
    abstract public function addEagerConstraints(array $models): void;
    abstract public function match(array $models, array $results, string $relation): array;

    public function getQuery(): QueryBuilder { return $this->query; }

    protected function getParentKey(): mixed
    {
        return $this->parent->{$this->localKey};
    }

    protected function getKeys(array $models, string $key): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn ($m) => $m->{$key}, $models)
        )));
    }

    /** Forward unknown calls to the underlying QueryBuilder. */
    public function __call(string $method, array $arguments): mixed
    {
        $result = $this->query->{$method}(...$arguments);
        return $result instanceof QueryBuilder ? $this : $result;
    }
}
