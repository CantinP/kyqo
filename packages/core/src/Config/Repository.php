<?php

namespace Kyqo\Core\Config;

use ArrayAccess;

/**
 * Configuration Repository
 *
 * Loads and manages all configuration files from the /config directory.
 * Supports dot-notation access, runtime overrides, and default values.
 */
class Repository implements ArrayAccess
{
    /**
     * All loaded configuration items.
     */
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Load configuration from a directory.
     */
    public function loadFromPath(string $path): void
    {
        foreach (glob($path.'/*.php') as $file) {
            $key = basename($file, '.php');
            $this->items[$key] = require $file;
        }
    }

    /**
     * Determine if the given configuration value exists.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Get the specified configuration value using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys  = explode('.', $key);
        $value = $this->items;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a given configuration value.
     */
    public function set(string $key, mixed $value): void
    {
        $keys    = explode('.', $key);
        $current = &$this->items;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    /**
     * Get all configuration items.
     */
    public function all(): array
    {
        return $this->items;
    }

    public function offsetExists(mixed $offset): bool   { return $this->has($offset); }
    public function offsetGet(mixed $offset): mixed      { return $this->get($offset); }
    public function offsetSet(mixed $offset, mixed $value): void { $this->set($offset, $value); }
    public function offsetUnset(mixed $offset): void     { unset($this->items[$offset]); }
}
