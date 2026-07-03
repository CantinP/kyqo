<?php

namespace Kyqo\UI;

/**
 * Kyqo UI Component Base
 *
 * The foundation of Kyqo's component system.
 * Inspired by React's functional components, Vue's SFCs,
 * and Angular's component class structure.
 *
 * Every Kyqo UI component extends this base class.
 */
abstract class Component
{
    /**
     * Component props (input data from parent).
     */
    protected array $props = [];

    /**
     * Component local state.
     */
    protected array $state = [];

    /**
     * Slots defined in this component.
     */
    protected array $slots = [];

    /**
     * CSS classes to apply to root element.
     */
    protected array $classes = [];

    /**
     * HTML attributes to apply to root element.
     */
    protected array $attributes = [];

    public function __construct(array $props = [], array $slots = [])
    {
        $this->props = $props;
        $this->slots = $slots;
        $this->setup();
    }

    /**
     * Component setup — called on instantiation.
     * Override to initialize state, computed values, etc.
     */
    protected function setup(): void
    {
        //
    }

    /**
     * Render the component — must return HTML string.
     */
    abstract public function render(): string;

    /**
     * Get a prop value.
     */
    public function prop(string $key, mixed $default = null): mixed
    {
        return $this->props[$key] ?? $default;
    }

    /**
     * Get a slot.
     */
    public function slot(string $name = 'default', string $fallback = ''): string
    {
        return $this->slots[$name] ?? $fallback;
    }

    /**
     * Get local state.
     */
    public function state(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    /**
     * Update local state.
     */
    public function setState(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    /**
     * Add a CSS class.
     */
    public function addClass(string ...$classes): static
    {
        $this->classes = array_merge($this->classes, $classes);
        return $this;
    }

    /**
     * Set an HTML attribute.
     */
    public function setAttribute(string $key, string $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Render class attribute string.
     */
    protected function classString(): string
    {
        return empty($this->classes) ? '' : ' class="'.implode(' ', $this->classes).'"';
    }

    /**
     * Render attribute string.
     */
    protected function attributeString(): string
    {
        $parts = [];
        foreach ($this->attributes as $key => $value) {
            $parts[] = htmlspecialchars($key).'="'.htmlspecialchars($value).'"';
        }
        return empty($parts) ? '' : ' '.implode(' ', $parts);
    }

    /**
     * Cast the component to string — triggers render.
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
