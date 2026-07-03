<?php

namespace Kyqo\Core\Container;

use Closure;
use ReflectionClass;
use Kyqo\Core\Contracts\Container as ContainerContract;
use Kyqo\Core\Exceptions\ContainerException;

class Container implements ContainerContract
{
    protected static ?self $instance = null;

    protected array $bindings         = [];
    protected array $instances        = [];
    protected array $aliases          = [];
    protected array $abstractAliases  = [];

    /** Only classes in these namespaces can be auto-resolved. */
    protected array $trustedNamespaces = ['Kyqo\\', 'App\\'];

    /** Classes blocked from container resolution regardless of namespace. */
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
     * Register an alias.
     * FIX BUG-NEW-2: Detect alias cycles before registering.
     */
    public function alias(string $abstract, string $alias): void
    {
        if ($abstract === $alias) {
            throw new ContainerException("[{$abstract}] cannot be aliased to itself.");
        }

        // Detect cycle: if $alias is already an alias chain that resolves to $abstract, refuse.
        $resolved = $alias;
        $visited  = [$abstract];
        while (isset($this->aliases[$resolved])) {
            $resolved = $this->aliases[$resolved];
            if (in_array($resolved, $visited, true)) {
                throw new ContainerException("Alias cycle detected: [{$abstract}] <-> [{$alias}].");
            }
            $visited[] = $resolved;
        }

        $this->aliases[$alias]               = $abstract;
        $this->abstractAliases[$abstract][]  = $alias;
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
                throw new ContainerException(
                    "Cannot resolve parameter [{$parameter->name}] for [{$type?->getName()}]."
                );
            }
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

    /**
     * FIX BUG-NEW-2: Iterative alias resolution — no recursion, no stack overflow.
     */
    protected function getAlias(string $abstract): string
    {
        $visited = [];
        while (isset($this->aliases[$abstract])) {
            if (isset($visited[$abstract])) {
                // Cycle detected at runtime — break out safely
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
