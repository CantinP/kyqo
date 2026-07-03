<?php

namespace Kyqo\Core\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * Kyqo Collection
 *
 * MINOR-FINAL-4: toJson() now uses JSON_THROW_ON_ERROR so encoding failures
 *               throw a JsonException instead of silently returning "false".
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    protected array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public static function make(array $items = []): static
    {
        return new static($items);
    }

    public function all(): array    { return $this->items; }
    public function count(): int    { return count($this->items); }
    public function isEmpty(): bool { return empty($this->items); }
    public function isNotEmpty(): bool { return !$this->isEmpty(); }

    public function first(callable $callback = null, mixed $default = null): mixed
    {
        if (is_null($callback)) {
            return empty($this->items) ? $default : reset($this->items);
        }
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) return $value;
        }
        return $default;
    }

    public function last(callable $callback = null, mixed $default = null): mixed
    {
        if (is_null($callback)) {
            return empty($this->items) ? $default : end($this->items);
        }
        return (new static(array_reverse($this->items, true)))->first($callback, $default);
    }

    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->items));
    }

    public function filter(callable $callback = null): static
    {
        if (is_null($callback)) {
            return new static(array_filter($this->items));
        }
        return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    public function reject(callable $callback): static
    {
        return $this->filter(fn ($v, $k) => !$callback($v, $k));
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key) === false) break;
        }
        return $this;
    }

    public function pluck(string $key): static
    {
        return $this->map(fn ($item) => is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null));
    }

    public function keyBy(string $key): static
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[is_array($item) ? $item[$key] : $item->$key] = $item;
        }
        return new static($result);
    }

    public function groupBy(string|callable $key): static
    {
        $result = [];
        foreach ($this->items as $item) {
            $groupKey = is_callable($key)
                ? $key($item)
                : (is_array($item) ? $item[$key] : $item->$key);
            $result[$groupKey][] = $item;
        }
        return new static(array_map(fn ($group) => new static($group), $result));
    }

    public function chunk(int $size): static
    {
        return new static(array_map(
            fn ($chunk) => new static($chunk),
            array_chunk($this->items, $size, true)
        ));
    }

    public function merge(array|self $items): static
    {
        return new static(array_merge(
            $this->items,
            $items instanceof self ? $items->all() : $items
        ));
    }

    public function unique(string|callable $key = null): static
    {
        if (is_null($key)) {
            return new static(array_unique($this->items));
        }
        $seen = [];
        return $this->filter(function ($item) use ($key, &$seen) {
            $val = is_callable($key)
                ? $key($item)
                : (is_array($item) ? $item[$key] : $item->$key);
            if (in_array($val, $seen, true)) return false;
            $seen[] = $val;
            return true;
        });
    }

    public function sortBy(string|callable $key, bool $descending = false): static
    {
        $items = $this->items;
        usort($items, function ($a, $b) use ($key, $descending) {
            $va = is_callable($key) ? $key($a) : (is_array($a) ? $a[$key] : $a->$key);
            $vb = is_callable($key) ? $key($b) : (is_array($b) ? $b[$key] : $b->$key);
            return $descending ? $vb <=> $va : $va <=> $vb;
        });
        return new static($items);
    }

    public function sortByDesc(string|callable $key): static { return $this->sortBy($key, true); }

    public function take(int $limit): static  { return new static(array_slice($this->items, 0, $limit)); }
    public function skip(int $offset): static { return new static(array_slice($this->items, $offset)); }
    public function values(): static          { return new static(array_values($this->items)); }
    public function keys(): static            { return new static(array_keys($this->items)); }

    public function toArray(): array { return $this->items; }

    /**
     * FIX MINOR-FINAL-4: Use JSON_THROW_ON_ERROR so failures are never silent.
     */
    public function toJson(): string
    {
        return json_encode($this->items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public function jsonSerialize(): mixed      { return $this->items; }
    public function getIterator(): ArrayIterator { return new ArrayIterator($this->items); }

    public function offsetExists(mixed $offset): bool  { return isset($this->items[$offset]); }
    public function offsetGet(mixed $offset): mixed     { return $this->items[$offset]; }
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) $this->items[] = $value;
        else $this->items[$offset] = $value;
    }
    public function offsetUnset(mixed $offset): void { unset($this->items[$offset]); }
}
