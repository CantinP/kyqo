<?php

namespace Kyqo\UI;

/**
 * Kyqo UI Component Base
 *
 * SEC-V4-3 FIX: classString() and attributeString() now escape all
 * user-controlled values with htmlspecialchars() to prevent XSS.
 */
abstract class Component
{
    protected array $props      = [];
    protected array $state      = [];
    protected array $slots      = [];
    protected array $classes    = [];
    protected array $attributes = [];

    public function __construct(array $props = [], array $slots = [])
    {
        $this->props = $props;
        $this->slots = $slots;
        $this->setup();
    }

    protected function setup(): void {}

    abstract public function render(): string;

    public function prop(string $key, mixed $default = null): mixed
    {
        return $this->props[$key] ?? $default;
    }

    public function slot(string $name = 'default', string $fallback = ''): string
    {
        return $this->slots[$name] ?? $fallback;
    }

    public function state(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    public function setState(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    public function addClass(string ...$classes): static
    {
        $this->classes = array_merge($this->classes, $classes);
        return $this;
    }

    public function setAttribute(string $key, string $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * SEC-V4-3 FIX: Each class is escaped before output.
     * Prevents XSS if a class was derived from user input.
     */
    protected function classString(): string
    {
        if (empty($this->classes)) {
            return '';
        }
        $escaped = array_map(
            fn (string $c) => htmlspecialchars($c, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $this->classes
        );
        return ' class="' . implode(' ', $escaped) . '"';
    }

    /**
     * SEC-V4-3 FIX: Both attribute keys and values are escaped.
     */
    protected function attributeString(): string
    {
        $parts = [];
        foreach ($this->attributes as $key => $value) {
            $safeKey   = htmlspecialchars((string) $key,   ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeValue = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $parts[]   = $safeKey . '="' . $safeValue . '"';
        }
        return empty($parts) ? '' : ' ' . implode(' ', $parts);
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
