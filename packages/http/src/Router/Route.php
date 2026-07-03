<?php

namespace Kyqo\Http\Router;

use Closure;
use Kyqo\Core\Application;

/**
 * Represents a single registered route.
 *
 * FIX minor-1: resolveController() no longer swallows DI failures silently.
 * Previously a bare `catch (\Throwable)` would fall back to `new $class()`
 * for ANY error — including misconfigured services — making bugs invisible.
 *
 * New behaviour:
 *   - If the app is not yet bootstrapped (getInstance() returns a plain
 *     Container with no bindings), fall back to direct instantiation as before.
 *   - If the app IS bootstrapped but make() throws, re-throw immediately so
 *     the Kernel's exception handler can surface the real error.
 */
class Route
{
    protected array   $methods;
    protected string  $uri;
    protected Closure|array|string $action;
    protected array   $middleware  = [];
    protected ?string $name        = null;
    protected array   $parameters  = [];

    /** Back-reference to the Router so name() can register named routes. */
    protected ?Router $router = null;

    public function __construct(array $methods, string $uri, Closure|array|string $action)
    {
        $this->methods = $methods;
        $this->uri     = $uri;
        $this->action  = $action;
    }

    /** Called by Router::addRoute() so the route can self-register named routes. */
    public function setRouter(Router $router): static
    {
        $this->router = $router;
        return $this;
    }

    /**
     * Assign a name and register it in the parent Router.
     */
    public function name(string $name): static
    {
        $this->name = $name;
        $this->router?->registerNamedRoute($name, $this->uri);
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
     */
    public function run(): mixed
    {
        if ($this->action instanceof Closure) {
            return ($this->action)(...array_values($this->parameters));
        }

        [$controllerClass, $method] = $this->resolveControllerAndMethod();
        $controller = $this->resolveController($controllerClass);
        return $controller->$method(...array_values($this->parameters));
    }

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
     * FIX minor-1: Distinguish between "app not yet bootstrapped" (safe fallback)
     * and "app bootstrapped but make() failed" (real error, must propagate).
     *
     * The heuristic: if the singleton instance is an Application (i.e. the full
     * bootstrap has run), we trust the container and let any exception bubble up.
     * If it is a bare Container (unit-test or early CLI context), we fall back to
     * direct instantiation — same as before, but only in that safe scenario.
     */
    protected function resolveController(string $class): object
    {
        $instance = Application::getInstance();

        if ($instance instanceof Application) {
            // Fully bootstrapped — propagate DI errors instead of hiding them.
            return $instance->make($class);
        }

        // Bare container (pre-bootstrap / tests) — direct instantiation fallback.
        try {
            return $instance->make($class);
        } catch (\Throwable) {
            return new $class();
        }
    }

    public function getName(): ?string     { return $this->name; }
    public function getMethods(): array    { return $this->methods; }
    public function getUri(): string       { return $this->uri; }
    public function getParameters(): array { return $this->parameters; }
    public function getMiddleware(): array { return $this->middleware; }
}
