<?php

namespace Kyqo\Http\Router;

use Closure;

/**
 * Represents a single registered route.
 */
class Route
{
    protected array $methods;
    protected string $uri;
    protected Closure|array|string $action;
    protected array $middleware = [];
    protected ?string $name = null;
    protected array $parameters = [];

    public function __construct(array $methods, string $uri, Closure|array|string $action)
    {
        $this->methods = $methods;
        $this->uri     = $uri;
        $this->action  = $action;
    }

    /**
     * Assign a name to this route.
     */
    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Assign middleware to this route.
     */
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
        if (!in_array($method, $this->methods)) {
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

        if (is_array($this->action)) {
            [$controller, $method] = $this->action;
            $ctrl = new $controller();
            return $ctrl->$method(...array_values($this->parameters));
        }

        if (is_string($this->action) && str_contains($this->action, '@')) {
            [$controller, $method] = explode('@', $this->action, 2);
            $ctrl = new $controller();
            return $ctrl->$method(...array_values($this->parameters));
        }

        return null;
    }

    public function getName(): ?string    { return $this->name; }
    public function getMethods(): array   { return $this->methods; }
    public function getUri(): string      { return $this->uri; }
    public function getParameters(): array { return $this->parameters; }
    public function getMiddleware(): array { return $this->middleware; }
}
