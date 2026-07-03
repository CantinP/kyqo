<?php

namespace Kyqo\Core\Contracts;

/**
 * Container Contract
 *
 * FIX BUG-FINAL-1 / MINOR-FINAL-1:
 * This interface was referenced in Container.php but never existed,
 * causing a fatal "Interface not found" error on every autoload.
 */
interface Container
{
    /**
     * Register a binding in the container.
     */
    public function bind(string $abstract, \Closure|string|null $concrete = null, bool $shared = false): void;

    /**
     * Register a shared (singleton) binding.
     */
    public function singleton(string $abstract, \Closure|string|null $concrete = null): void;

    /**
     * Register an existing instance as a singleton.
     */
    public function instance(string $abstract, mixed $instance): mixed;

    /**
     * Resolve a binding out of the container.
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /**
     * Instantiate a concrete class or closure.
     */
    public function build(\Closure|string $concrete, array $parameters = []): mixed;

    /**
     * Register an alias for an abstract.
     */
    public function alias(string $abstract, string $alias): void;

    /**
     * Determine if the abstract is bound.
     */
    public function bound(string $abstract): bool;
}
