<?php

namespace Kyqo\Database\Orm;

use Kyqo\Database\DatabaseManager;
use Kyqo\Database\Orm\Concerns\HasRelations;
use Kyqo\Database\Orm\Concerns\HasCasts;

/**
 * Base ORM Model.
 *
 * Provides Active-Record-style CRUD, mass assignment, timestamps,
 * attribute casting, and relation support.
 *
 * Usage:
 *   class Post extends Model {
 *       protected string $table    = 'posts';
 *       protected array  $fillable = ['title', 'body'];
 *       protected array  $casts    = ['published' => 'bool'];
 *   }
 *
 *   Post::create(['title' => 'Hello']);
 *   Post::where('published', true)->get();
 *   $post->user;  // belongsTo auto-loaded via __get
 */
class Model
{
    use HasRelations;
    use HasCasts;

    protected string  $table            = '';
    protected string  $primaryKey       = 'id';
    protected bool    $incrementing     = true;
    protected bool    $timestamps       = true;
    protected array   $fillable         = [];
    protected array   $guarded          = ['*'];
    protected array   $hidden           = [];
    protected array   $attributes       = [];
    protected array   $original         = [];
    protected bool    $exists           = false;

    protected static ?DatabaseManager $resolver = null;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    // ---- Static helpers -------------------------------------------------------

    public static function setConnectionResolver(DatabaseManager $resolver): void
    {
        static::$resolver = $resolver;
    }

    protected static function getResolver(): DatabaseManager
    {
        if (static::$resolver !== null) {
            return static::$resolver;
        }
        return \Kyqo\Core\Application::getInstance()->make(DatabaseManager::class);
    }

    public function getTable(): string
    {
        if ($this->table !== '') return $this->table;
        $base = strtolower(class_basename(static::class));
        return $base . 's';
    }

    public function getPrimaryKey(): string { return $this->primaryKey; }

    public static function query(): ModelQueryBuilder
    {
        return (new static())->newQuery();
    }

    public function newQuery(): ModelQueryBuilder
    {
        $connection = static::getResolver()->connection();
        $qb         = $connection->table($this->getTable());
        return new ModelQueryBuilder($qb, static::class);
    }

    public function newQueryWithoutScopes(): ModelQueryBuilder
    {
        return $this->newQuery();
    }

    // ---- CRUD ---------------------------------------------------------------

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find(mixed $id): ?static
    {
        return static::query()->where((new static())->getPrimaryKey(), '=', $id)->first();
    }

    public static function findOrFail(mixed $id): static
    {
        $model = static::find($id);
        if ($model === null) {
            throw new \RuntimeException(static::class . " with ID [{$id}] not found.");
        }
        return $model;
    }

    public static function create(array $attributes): static
    {
        $instance = new static($attributes);
        $instance->save();
        return $instance;
    }

    /**
     * FIX B4 – Apply ALL $attributes columns as WHERE conditions, not just the first.
     */
    public static function firstOrCreate(array $attributes, array $values = []): static
    {
        $qb = static::query();
        foreach ($attributes as $col => $val) {
            $qb->where($col, '=', $val);
        }
        $instance = $qb->first();
        if ($instance !== null) return $instance;
        return static::create(array_merge($attributes, $values));
    }

    /**
     * FIX B4 – Apply ALL $attributes columns as WHERE conditions, not just the first.
     */
    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        $qb = static::query();
        foreach ($attributes as $col => $val) {
            $qb->where($col, '=', $val);
        }
        $instance = $qb->first();
        if ($instance !== null) {
            $instance->update($values);
            return $instance;
        }
        return static::create(array_merge($attributes, $values));
    }

    public function save(): bool
    {
        $connection = static::getResolver()->connection();
        $this->fireTimestamps();

        $data = $this->getDirtyForPersistence();

        if ($this->exists) {
            if (empty($data)) return true;
            $connection->table($this->getTable())
                ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey])
                ->update($data);
        } else {
            $id = $connection->table($this->getTable())->insertGetId($data);
            if ($this->incrementing) {
                $this->attributes[$this->primaryKey] = $id;
            }
            $this->exists = true;
        }

        $this->syncOriginal();
        return true;
    }

    public function update(array $attributes): bool
    {
        $this->fill($attributes);
        return $this->save();
    }

    public function delete(): bool
    {
        if (!$this->exists) return false;
        $connection = static::getResolver()->connection();
        $connection->table($this->getTable())
            ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey])
            ->delete();
        $this->exists = false;
        return true;
    }

    // ---- Attributes ---------------------------------------------------------

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        if ($this->hasCast($key)) {
            $value = $this->decastAttribute($key, $value);
        }
        $this->attributes[$key] = $value;
        return $this;
    }

    public function getAttribute(string $key): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            $value = $this->attributes[$key];
            if ($this->hasCast($key)) {
                return $this->castAttribute($key, $value);
            }
            return $value;
        }
        // Lazy-load relation
        if (method_exists($this, $key)) {
            if (!$this->relationLoaded($key)) {
                $result = $this->{$key}()->getResults();
                $this->setRelation($key, $result);
            }
            return $this->relations[$key];
        }
        return null;
    }

    public function getAttributes(): array { return $this->attributes; }

    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    public function isDirty(string ...$keys): bool
    {
        $dirty = $this->getDirty();
        if (empty($keys)) return !empty($dirty);
        foreach ($keys as $key) {
            if (array_key_exists($key, $dirty)) return true;
        }
        return false;
    }

    public function syncOriginal(): static
    {
        $this->original = $this->attributes;
        return $this;
    }

    protected function getDirtyForPersistence(): array
    {
        return $this->exists ? $this->getDirty() : $this->attributes;
    }

    protected function isFillable(string $key): bool
    {
        if (!empty($this->fillable)) {
            return in_array($key, $this->fillable, true);
        }
        if (in_array('*', $this->guarded, true)) return false;
        return !in_array($key, $this->guarded, true);
    }

    protected function fireTimestamps(): void
    {
        if (!$this->timestamps) return;
        $now = date('Y-m-d H:i:s');
        if (!$this->exists) {
            $this->attributes['created_at'] = $this->attributes['created_at'] ?? $now;
        }
        $this->attributes['updated_at'] = $now;
    }

    // ---- Serialization ------------------------------------------------------

    public function toArray(): array
    {
        $arr = [];
        foreach ($this->attributes as $key => $value) {
            if (in_array($key, $this->hidden, true)) continue;
            $arr[$key] = $this->hasCast($key) ? $this->castAttribute($key, $value) : $value;
        }
        foreach ($this->relations as $key => $value) {
            if (is_array($value)) {
                $arr[$key] = array_map(fn ($m) => $m instanceof self ? $m->toArray() : $m, $value);
            } else {
                $arr[$key] = $value instanceof self ? $value->toArray() : $value;
            }
        }
        return $arr;
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags);
    }

    public function __get(string $key): mixed  { return $this->getAttribute($key); }
    public function __set(string $key, mixed $value): void { $this->setAttribute($key, $value); }
    public function __isset(string $key): bool { return isset($this->attributes[$key]); }
    public function __unset(string $key): void { unset($this->attributes[$key]); }

    // ---- Static proxy -------------------------------------------------------

    public static function __callStatic(string $method, array $args): mixed
    {
        return (new static())->newQuery()->{$method}(...$args);
    }

    public static function where(string $column, string $operator, mixed $value = null): ModelQueryBuilder
    {
        return static::query()->where($column, $operator, $value);
    }

    public static function whereIn(string $column, array $values): ModelQueryBuilder
    {
        return static::query()->whereIn($column, $values);
    }

    public static function orderBy(string $column, string $direction = 'ASC'): ModelQueryBuilder
    {
        return static::query()->orderBy($column, $direction);
    }

    public static function limit(int $n): ModelQueryBuilder
    {
        return static::query()->limit($n);
    }

    public static function paginate(int $perPage = 15, int $page = 1): array
    {
        return static::query()->paginate($perPage, $page);
    }
}
