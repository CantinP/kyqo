<?php

namespace Kyqo\Database\Orm;

use JsonSerializable;
use Kyqo\Core\Application;
use Kyqo\Database\DatabaseManager;
use Kyqo\Database\QueryBuilder;

/**
 * Kyqo ORM Base Model — full ActiveRecord implementation.
 *
 * Supports:
 *  - Static query: Model::find(), Model::all(), Model::where(), Model::create()
 *  - Instance: save(), update(), delete(), fresh()
 *  - Attribute casting, dirty tracking, hidden fields, fillable guard
 *  - Automatic timestamps (created_at / updated_at)
 */
abstract class Model implements JsonSerializable
{
    protected string $table;
    protected string $primaryKey  = 'id';
    protected bool   $timestamps  = true;
    protected array  $fillable    = [];
    protected array  $guarded     = ['*'];
    protected array  $hidden      = [];
    protected array  $casts       = [];
    protected array  $attributes  = [];
    protected array  $original    = [];
    protected bool   $exists      = false;

    // ---- Constructor --------------------------------------------------------

    public function __construct(array $attributes = [])
    {
        if (!isset($this->table)) {
            $this->table = $this->guessTable();
        }
        $this->fill($attributes);
        $this->syncOriginal();
    }

    // ---- Static query API ---------------------------------------------------

    public static function query(): QueryBuilder
    {
        return static::db()->table((new static())->getTable());
    }

    public static function all(): array
    {
        return array_map(
            fn (array $row) => static::hydrate($row),
            static::query()->get()
        );
    }

    public static function find(mixed $id): ?static
    {
        $model = new static();
        $row   = static::query()->find($id, $model->primaryKey);
        return $row ? static::hydrate($row) : null;
    }

    public static function findOrFail(mixed $id): static
    {
        $model = static::find($id);
        if ($model === null) {
            throw new \RuntimeException(
                static::class . ' with id [' . $id . '] not found.'
            );
        }
        return $model;
    }

    public static function where(string $column, mixed $operatorOrValue, mixed $value = null): QueryBuilder
    {
        $qb = static::query();
        return $value === null
            ? $qb->where($column, $operatorOrValue)
            : $qb->where($column, $operatorOrValue, $value);
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public static function firstOrCreate(array $conditions, array $extra = []): static
    {
        $qb = static::query();
        foreach ($conditions as $col => $val) {
            $qb->where($col, $val);
        }
        $row = $qb->first();
        if ($row !== null) {
            return static::hydrate($row);
        }
        return static::create(array_merge($conditions, $extra));
    }

    // ---- Instance CRUD ------------------------------------------------------

    public function save(): bool
    {
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            if (!$this->exists) {
                $this->attributes['created_at'] = $now;
            }
            $this->attributes['updated_at'] = $now;
        }

        $qb = static::db()->table($this->table);

        if ($this->exists) {
            $dirty = $this->getDirty();
            if (empty($dirty)) {
                return true;
            }
            $qb->where($this->primaryKey, $this->attributes[$this->primaryKey])->update($dirty);
        } else {
            $id = $qb->insertGetId($this->attributes);
            $this->attributes[$this->primaryKey] = $id;
            $this->exists = true;
        }

        $this->syncOriginal();
        return true;
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }
        static::db()->table($this->table)
            ->where($this->primaryKey, $this->attributes[$this->primaryKey])
            ->delete();
        $this->exists = false;
        return true;
    }

    public function fresh(): static
    {
        return static::findOrFail($this->attributes[$this->primaryKey]);
    }

    public function update(array $attributes): bool
    {
        $this->fill($attributes);
        return $this->save();
    }

    // ---- Attribute management -----------------------------------------------

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->castAttribute($key, $this->attributes[$key] ?? $default);
    }

    public function syncOriginal(): static
    {
        $this->original = $this->attributes;
        return $this;
    }

    public function isDirty(?string $key = null): bool
    {
        if ($key !== null) {
            return ($this->attributes[$key] ?? null) !== ($this->original[$key] ?? null);
        }
        foreach ($this->attributes as $k => $v) {
            if (!array_key_exists($k, $this->original) || $this->original[$k] !== $v) {
                return true;
            }
        }
        return false;
    }

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

    public function getOriginal(?string $key = null, mixed $default = null): mixed
    {
        return $key !== null ? ($this->original[$key] ?? $default) : $this->original;
    }

    public function isFillable(string $key): bool
    {
        if (in_array($key, $this->guarded, true)) {
            return false;
        }
        if ($this->guarded === ['*']) {
            return !empty($this->fillable) && in_array($key, $this->fillable, true);
        }
        return true;
    }

    public function getTable(): string  { return $this->table; }
    public function getKey(): mixed     { return $this->attributes[$this->primaryKey] ?? null; }
    public function exists(): bool      { return $this->exists; }

    // ---- Casting ------------------------------------------------------------

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if (!isset($this->casts[$key])) {
            return $value;
        }
        return match ($this->casts[$key]) {
            'int', 'integer'   => (int) $value,
            'float', 'double'  => (float) $value,
            'bool', 'boolean'  => (bool) $value,
            'array', 'json'    => is_string($value) ? json_decode($value, true) : $value,
            'datetime'         => $value ? new \DateTimeImmutable($value) : null,
            'hashed'           => $value,
            default            => $value,
        };
    }

    // ---- Serialization ------------------------------------------------------

    public function toArray(): array
    {
        $attributes = $this->attributes;
        foreach ($this->hidden as $key) {
            unset($attributes[$key]);
        }
        return $attributes;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function jsonSerialize(): mixed { return $this->toArray(); }
    public function __get(string $key): mixed  { return $this->getAttribute($key); }
    public function __set(string $key, mixed $value): void { $this->setAttribute($key, $value); }
    public function __isset(string $key): bool { return isset($this->attributes[$key]); }

    // ---- Hydration ----------------------------------------------------------

    protected static function hydrate(array $row): static
    {
        $model             = new static();
        $model->attributes = $row;
        $model->exists     = true;
        $model->syncOriginal();
        return $model;
    }

    // ---- DB connection ------------------------------------------------------

    protected static function db(): DatabaseManager
    {
        return Application::getInstance()->make(DatabaseManager::class);
    }

    // ---- Table name guess ---------------------------------------------------

    protected function guessTable(): string
    {
        $class = basename(str_replace('\\', '/', static::class));
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class)) . 's';
    }
}
