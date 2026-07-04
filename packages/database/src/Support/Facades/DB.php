<?php

namespace Kyqo\Database\Support\Facades;

use Kyqo\Core\Support\Facades\Facade;

/**
 * @method static \Kyqo\Database\QueryBuilder table(string $table)
 * @method static mixed select(string $query, array $bindings = [])
 * @method static bool statement(string $query, array $bindings = [])
 * @method static int affectingStatement(string $query, array $bindings = [])
 * @method static mixed transaction(\Closure $callback)
 * @method static void beginTransaction()
 * @method static void commit()
 * @method static void rollBack()
 * @method static \Kyqo\Database\Schema\SchemaBuilder schema()
 */
class DB extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'db';
    }
}
