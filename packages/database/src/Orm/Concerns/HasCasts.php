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
 *
 * FIX AUDIT-9: json_decode now uses JSON_THROW_ON_ERROR and is wrapped
 *              in a try/catch. A malformed JSON string returns null with
 *              no silent data corruption.
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

            // FIX AUDIT-9: JSON_THROW_ON_ERROR — malformed JSON returns null.
            in_array($type, ['array', 'json'])              => $this->decodeJson($value, true),
            $type === 'object'                              => $this->decodeJson($value, false),

            $type === 'collection'                          => new \Kyqo\Core\Support\Collection(
                                                                  $this->decodeJson($value, true) ?? []
                                                              ),
            in_array($type, ['date', 'datetime'])           => $this->castDateTime($value),
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

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * FIX AUDIT-9: Safe JSON decode wrapper.
     *
     * Returns null (instead of corrupted data) when the stored value is not
     * valid JSON. Uses JSON_THROW_ON_ERROR so the failure path is explicit.
     *
     * @param  mixed $value  Value from the database column.
     * @param  bool  $assoc  true → array, false → stdClass|null.
     * @return array|\stdClass|null
     */
    private function decodeJson(mixed $value, bool $assoc): array|\stdClass|null
    {
        if (!is_string($value)) {
            return $assoc ? (array) $value : (object) $value;
        }

        try {
            return json_decode($value, $assoc, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * Safe DateTimeImmutable constructor — returns null on an invalid string.
     */
    private function castDateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
