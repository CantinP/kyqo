<?php

namespace Kyqo\Http\Router;

use Closure;
use Kyqo\Core\Container\Container;

/**
 * Represents a single registered route.
 *
 * BUG FIX: Controllers are now resolved through the IoC Container
 *          so constructor dependencies are properly injected.
 */
class Route
{
    protected array $methods;
    protected string $uri;
    protected Closure|array|string $action;
    protected array $middleware  = [];
    protected ?string $name      = null;
    protected array $parameters  = [];

    public function __construct(array $methods, string $uri, Closure|array|string $action)
    {
        $this->methods = $methods;
        $this->uri     = $uri;
        $this->action  = $action;
    }

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function middleware(string|array $middleware): static
    {
        $this->middleware = array_merge(
            $this->middleware,
            is_array($middleware) ? $middleware : [$middleware]
        );
        return $this;
    }

    /**
     * Check if this route matches the given method and URI.
     */
    public function matches(string $method, string $uri): bool
    {
        if (!in_array($method, $this->methods, true)) {
            return false;
        }

        $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', '/' . ltrim($this->uri, '/'));
        $pattern = '@^' . $pattern . '$@';

        if (preg_match($pattern, $uri, $matches)) {
            array_shift($matches);
            preg_match_all('/\{([^}]+)\}/', $this->uri, $paramNames);
            $this->parameters = array_combine($paramNames[1], $matches) ?: [];
            return true;
        }

        return false;
    }

    /**
     * Run the route action.
     *
     * BUG FIX: Controllers are resolved through the Container so their
     *          constructor dependencies are injected automatically.
     */
    public function run(): mixed
    {
        if ($this->action instanceof Closure) {
            return ($this->action)(...array_values($this->parameters));
        }

        [$controllerClass, $method] = $this->resolveControllerAndMethod();

        // BUG FIX: Use the IoC container to instantiate controllers
        $controller = $this->resolveController($controllerClass);

        return $controller->$method(...array_values($this->parameters));
    }

    /**
     * Resolve controller class and method from various action formats.
     */
    protected function resolveControllerAndMethod(): array
    {
        if (is_array($this->action)) {
            return [$this->action[0], $this->action[1]];
        }

        if (is_string($this->action) && str_contains($this->action, '@')) {
            return explode('@', $this->action, 2);
        }

        throw new \RuntimeException('Invalid route action format.');
    }

    /**
     * Resolve a controller through the IoC container if available,
     * otherwise fall back to direct instantiation.
     */
    protected function resolveController(string $class): object
    {
        // Use the container singleton if available
        try {
            $container = Container::getInstance();
            if ($container !== null && $container->bound($class)) {
                return $container->make($class);
            }
            // Auto-resolve through the container (handles constructor DI)
            return $container->build($class);
        } catch (\Throwable) {
            // Fallback: direct instantiation (no DI)
            return new $class();
        }
    }

    public function getName(): ?string     { return $this->name; }
    public function getMethods(): array    { return $this->methods; }
    public function getUri(): string       { return $this->uri; }
    public function getParameters(): array { return $this->parameters; }
    public function getMiddleware(): array { return $this->middleware; }
}
