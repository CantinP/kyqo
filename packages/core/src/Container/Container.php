<?php

namespace Kyqo\Core\Container;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use Kyqo\Core\Contracts\Container as ContainerContract;
use Kyqo\Core\Exceptions\ContainerException;

class Container implements ContainerContract
{
    protected static ?self $instance = null;

    protected array $bindings        = [];
    protected array $instances       = [];
    protected array $aliases         = [];
    protected array $abstractAliases = [];

    protected array $trustedNamespaces = ['Kyqo\\', 'App\\'];

    protected array $blockedClasses = [
        'PDO', 'SplFileObject', 'DirectoryIterator',
        'RecursiveDirectoryIterator', 'FilesystemIterator',
        'GlobIterator', 'SimpleXMLElement', 'DOMDocument',
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        $this->dropStaleInstances($abstract);
        $concrete ??= $abstract;
        if (!$concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }
        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, mixed $instance): mixed
    {
        $this->instances[$abstract] = $instance;
        return $instance;
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);
        $object   = $this->build($concrete, $parameters);

        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    public function build(Closure|string $concrete, array $parameters = []): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        $this->guardAgainstDangerousClass($concrete);

        if (!isset($this->bindings[$concrete])) {
            $this->guardNamespace($concrete);
        }

        $reflector = new ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Class [{$concrete}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();
        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);
        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Call a callable, injecting dependencies for typed parameters.
     *
     * FIX C1: properly distinguishes:
     *   - [object, method]   → ReflectionMethod::invokeArgs($object, $args)
     *   - 'Class::method'    → detect static vs instance; throw for non-static without object
     *   - Closure / callable → ReflectionFunction::invokeArgs($args)
     *
     * @param callable|array $callable  Any PHP callable.
     * @param array          $primitives Named overrides for primitive parameters.
     */
    public function call(callable|array $callable, array $primitives = []): mixed
    {
        // [object|string, method]
        if (is_array($callable)) {
            [$objectOrClass, $method] = $callable;
            $reflection   = new ReflectionMethod($objectOrClass, $method);
            $dependencies = $this->resolveDependencies($reflection->getParameters(), $primitives);

            if ($reflection->isStatic()) {
                return $reflection->invokeArgs(null, $dependencies);
            }

            $object = is_object($objectOrClass)
                ? $objectOrClass
                : $this->make($objectOrClass);

            return $reflection->invokeArgs($object, $dependencies);
        }

        // 'Class::method' string
        if (is_string($callable) && str_contains($callable, '::')) {
            [$class, $method] = explode('::', $callable, 2);
            $reflection   = new ReflectionMethod($class, $method);
            $dependencies = $this->resolveDependencies($reflection->getParameters(), $primitives);

            if ($reflection->isStatic()) {
                return $reflection->invokeArgs(null, $dependencies);
            }

            // Non-static: resolve the class from the container
            $object = $this->make($class);
            return $reflection->invokeArgs($object, $dependencies);
        }

        // Closure or any other callable
        $reflection   = new ReflectionFunction(Closure::fromCallable($callable));
        $dependencies = $this->resolveDependencies($reflection->getParameters(), $primitives);
        return $reflection->invokeArgs($dependencies);
    }

    public function alias(string $abstract, string $alias): void
    {
        if ($abstract === $alias) {
            throw new ContainerException("[{$abstract}] cannot be aliased to itself.");
        }

        $resolved = $alias;
        $visited  = [$abstract];
        while (isset($this->aliases[$resolved])) {
            $resolved = $this->aliases[$resolved];
            if (in_array($resolved, $visited, true)) {
                throw new ContainerException("Alias cycle detected: [{$abstract}] <-> [{$alias}].");
            }
            $visited[] = $resolved;
        }

        $this->aliases[$alias]              = $abstract;
        $this->abstractAliases[$abstract][] = $alias;
    }

    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract])
            || isset($this->instances[$abstract])
            || $this->isAlias($abstract);
    }

    public static function getInstance(): static
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public static function setInstance(?self $container = null): ?static
    {
        static::$instance = $container;
        return static::$instance;
    }

    // -------------------------------------------------------------------------
    // Protected helpers
    // -------------------------------------------------------------------------

    protected function guardAgainstDangerousClass(string $class): void
    {
        $normalized = ltrim($class, '\\');
        foreach ($this->blockedClasses as $blocked) {
            if ($normalized === ltrim($blocked, '\\')) {
                throw new ContainerException(
                    "Security: class [{$class}] is blocked from container resolution."
                );
            }
        }
    }

    protected function guardNamespace(string $class): void
    {
        foreach ($this->trustedNamespaces as $ns) {
            if (str_starts_with($class, $ns)) {
                return;
            }
        }
        throw new ContainerException(
            "Security: class [{$class}] is not in a trusted namespace and is not explicitly bound."
        );
    }

    /**
     * Resolve constructor/method parameter dependencies.
     *
     * FIX AUDIT (PHP 8+ union/intersection types):
     * ReflectionParameter::getType() can return a ReflectionUnionType or
     * ReflectionIntersectionType on PHP 8.0+ / 8.1+. Neither has isBuiltin()
     * nor getName(), so calling those methods would throw a fatal error.
     *
     * Resolution strategy for non-named types:
     *   - If the parameter has a default value, use it.
     *   - Otherwise throw a ContainerException with a helpful message.
     *
     * Only ReflectionNamedType is handled for auto-wiring; union/intersection
     * types require an explicit primitive override to be injected.
     */
    protected function resolveDependencies(array $parameters, array $primitives = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            // Explicit primitive override always wins.
            if (array_key_exists($parameter->name, $primitives)) {
                $dependencies[] = $primitives[$parameter->name];
                continue;
            }

            $type = $parameter->getType();

            // FIX: only auto-wire when the type is a single named class/interface.
            // ReflectionUnionType (string|int) and ReflectionIntersectionType
            // (A&B) are not auto-wireable — fall through to default value.
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            $typeName = $type instanceof ReflectionNamedType
                ? $type->getName()
                : ($type !== null ? (string) $type : 'mixed');

            throw new ContainerException(
                "Cannot resolve parameter [\${$parameter->name}] of type [{$typeName}]."
                . ' Provide a primitive override or add a default value.'
            );
        }

        return $dependencies;
    }

    protected function getClosure(string $abstract, string $concrete): Closure
    {
        return function (self $container, array $parameters = []) use ($concrete) {
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
        $visited = [];
        while (isset($this->aliases[$abstract])) {
            if (isset($visited[$abstract])) {
                break;
            }
            $visited[$abstract] = true;
            $abstract = $this->aliases[$abstract];
        }
        return $abstract;
    }

    protected function dropStaleInstances(string $abstract): void
    {
        unset($this->instances[$abstract]);
    }
}
