<?php

namespace Kyqo\Core\Container;

use Closure;
use ReflectionClass;
use ReflectionParameter;
use Kyqo\Core\Contracts\Container as ContainerContract;

/**
 * Kyqo IoC Container
 *
 * Handles binding, resolving and injecting dependencies throughout the
 * entire framework. Inspired by Laravel's Service Container.
 */
class Container implements ContainerContract
{
    /**
     * The current globally available container instance.
     */
    protected static ?self $instance = null;

    /**
     * All registered bindings.
     */
    protected array $bindings = [];

    /**
     * All registered singletons.
     */
    protected array $instances = [];

    /**
     * All registered aliases.
     */
    protected array $aliases = [];

    /**
     * All registered abstract alias maps.
     */
    protected array $abstractAliases = [];

    /**
     * Bind an abstract to a concrete implementation.
     */
    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        $this->dropStaleInstances($abstract);

        $concrete ??= $abstract;

        if (!$concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    /**
     * Register a shared binding (singleton) in the container.
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance as a singleton.
     */
    public function instance(string $abstract, mixed $instance): mixed
    {
        $this->instances[$abstract] = $instance;
        return $instance;
    }

    /**
     * Resolve a given abstract from the container.
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        $object = $this->build($concrete, $parameters);

        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Instantiate a concrete instance.
     */
    public function build(Closure|string $concrete, array $parameters = []): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        $reflector = new ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Class [{$concrete}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete;
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Register an alias.
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias]     = $abstract;
        $this->abstractAliases[$abstract][] = $alias;
    }

    /**
     * Check if an abstract is bound.
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract])
            || isset($this->instances[$abstract])
            || $this->isAlias($abstract);
    }

    /**
     * Get the globally available instance of the container.
     */
    public static function getInstance(): static
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Set the globally available container instance.
     */
    public static function setInstance(?self $container = null): static|null
    {
        return static::$instance = $container;
    }

    /**
     * Resolve all dependencies for a constructor.
     */
    protected function resolveDependencies(array $parameters, array $primitives = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            if (array_key_exists($parameter->name, $primitives)) {
                $dependencies[] = $primitives[$parameter->name];
                continue;
            }

            $type = $parameter->getType();

            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \Exception("Cannot resolve parameter [{$parameter->name}].");
            }
        }

        return $dependencies;
    }

    protected function getClosure(string $abstract, string $concrete): Closure
    {
        return function (self $container, array $parameters = []) use ($abstract, $concrete) {
            return $container->build($concrete, $parameters);
        };
    }

    protected function getConcrete(string $abstract): Closure|string
    {
        return $this->bindings[$abstract]['concrete'] ?? $abstract;
    }

    protected function isShared(string $abstract): bool
    {
        return isset($this->instances[$abstract])
            || ($this->bindings[$abstract]['shared'] ?? false);
    }

    protected function isAlias(string $name): bool
    {
        return isset($this->aliases[$name]);
    }

    protected function getAlias(string $abstract): string
    {
        return isset($this->aliases[$abstract])
            ? $this->getAlias($this->aliases[$abstract])
            : $abstract;
    }

    protected function dropStaleInstances(string $abstract): void
    {
        unset($this->instances[$abstract]);
    }
}
