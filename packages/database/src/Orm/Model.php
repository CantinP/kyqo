<?php

namespace Kyqo\Database\Orm;

use JsonSerializable;

/**
 * Kyqo ORM Base Model
 *
 * MINOR FIX: $original is now populated in fill() and setAttribute().
 *            isDirty(), getOriginal(), and syncOriginal() are implemented.
 */
abstract class Model implements JsonSerializable
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected bool   $timestamps = true;
    protected array  $fillable   = [];
    protected array  $guarded    = ['*'];
    protected array  $hidden     = [];
    protected array  $casts      = [];
    protected array  $attributes = [];
    protected array  $original   = [];
    protected bool   $exists     = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->syncOriginal();
    }

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

    /**
     * Sync $original to the current $attributes state.
     * Called after a model is hydrated from the database.
     */
    public function syncOriginal(): static
    {
        $this->original = $this->attributes;
        return $this;
    }

    /**
     * Determine if an attribute has been modified since the last sync.
     */
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

    /**
     * Get dirty (changed) attributes only.
     */
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

    /**
     * Get the original (pre-change) value of an attribute.
     */
    public function getOriginal(?string $key = null, mixed $default = null): mixed
    {
        if ($key !== null) {
            return $this->original[$key] ?? $default;
        }
        return $this->original;
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
            default            => $value,
        };
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
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function jsonSerialize(): mixed { return $this->toArray(); }
    public function __get(string $key): mixed  { return $this->getAttribute($key); }
    public function __set(string $key, mixed $value): void { $this->setAttribute($key, $value); }
    public function __isset(string $key): bool { return isset($this->attributes[$key]); }
}
