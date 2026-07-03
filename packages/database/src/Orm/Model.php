<?php

namespace Kyqo\Database\Orm;

use JsonSerializable;

/**
 * Kyqo ORM Base Model
 *
 * Active record implementation for database models. Supports fillable/guarded
 * properties, casting, hidden fields, and basic CRUD operations.
 * Inspired by Laravel Eloquent.
 */
abstract class Model implements JsonSerializable
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected bool $timestamps = true;
    protected array $fillable = [];
    protected array $guarded = ['*'];
    protected array $hidden = [];
    protected array $casts = [];
    protected array $attributes = [];
    protected array $original = [];
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Fill attributes (respects fillable/guarded).
     */
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

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if (!isset($this->casts[$key])) return $value;

        return match ($this->casts[$key]) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json' => is_string($value) ? json_decode($value, true) : $value,
            'datetime' => $value ? new \DateTimeImmutable($value) : null,
            default => $value,
        };
    }

    public function isFillable(string $key): bool
    {
        if (in_array($key, $this->guarded)) return false;
        if ($this->guarded === ['*']) return !empty($this->fillable) && in_array($key, $this->fillable);
        return true;
    }

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
        return json_encode($this->toArray());
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }
}
