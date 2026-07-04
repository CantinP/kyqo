<?php

namespace Kyqo\Database\Orm\Concerns;

/**
 * HasCasts trait.
 *
 * Declares a $casts array on a model to automatically cast attributes
 * to native PHP types on get and back to DB-safe values on set.
 *
 * Supported cast types:
 *   int | integer, float | double | real, string, bool | boolean,
 *   array, object, collection, date, datetime, timestamp, json
 *
 * Usage:
 *   protected array $casts = [
 *       'is_admin'    => 'bool',
 *       'score'       => 'float',
 *       'meta'        => 'array',
 *       'published_at'=> 'datetime',
 *   ];
 */
trait HasCasts
{
    protected array $casts = [];

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) return null;

        $type = strtolower($this->casts[$key] ?? '');

        return match (true) {
            in_array($type, ['int', 'integer'])             => (int)   $value,
            in_array($type, ['float', 'double', 'real'])    => (float) $value,
            $type === 'string'                              => (string) $value,
            in_array($type, ['bool', 'boolean'])            => (bool)  $value,
            in_array($type, ['array', 'json'])              => is_string($value) ? json_decode($value, true) : (array) $value,
            $type === 'object'                              => is_string($value) ? json_decode($value) : (object) $value,
            $type === 'collection'                          => new \Kyqo\Core\Support\Collection(
                                                                  is_string($value) ? (json_decode($value, true) ?? []) : (array) $value
                                                              ),
            in_array($type, ['date', 'datetime'])           => new \DateTimeImmutable($value),
            $type === 'timestamp'                           => is_numeric($value) ? (int) $value : strtotime($value),
            default                                         => $value,
        };
    }

    protected function decastAttribute(string $key, mixed $value): mixed
    {
        $type = strtolower($this->casts[$key] ?? '');

        return match (true) {
            in_array($type, ['array', 'json', 'object', 'collection']) =>
                is_string($value) ? $value : json_encode($value),
            in_array($type, ['date', 'datetime']) && $value instanceof \DateTimeInterface =>
                $value->format('Y-m-d H:i:s'),
            $type === 'timestamp' && $value instanceof \DateTimeInterface =>
                $value->getTimestamp(),
            default => $value,
        };
    }

    public function hasCast(string $key): bool
    {
        return isset($this->casts[$key]);
    }

    public function getCasts(): array { return $this->casts; }
}
