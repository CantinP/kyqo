<?php

namespace Kyqo\Http\Router;

use Closure;
use Kyqo\Core\Application;

/**
 * Represents a single registered route.
 *
 * FIX BUG-NEW-3: Controllers are resolved through Application::getInstance()
 *               (the fully bootstrapped app container), NOT bare Container::getInstance().
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
     * MINOR FIX: namedRoutes was never populated before.
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
     * FIX BUG-NEW-3: Uses Application singleton (the real bootstrapped container).
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
     * Resolve controller via Application (fully bootstrapped container).
     * Falls back to direct instantiation only if app is not booted yet.
     */
    protected function resolveController(string $class): object
    {
        try {
            $app = Application::getInstance();
            return $app->make($class);
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
