<?php

namespace Kyqo\Http\Router;

use Closure;

/**
 * Kyqo Router
 *
 * Handles HTTP route registration, parameter extraction, named routes,
 * route groups, middleware stacks, and route dispatching.
 */
class Router
{
    protected array $routes = [];
    protected array $namedRoutes = [];
    protected array $groupStack = [];

    protected array $verbs = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    public function get(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    public function post(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    public function put(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    public function patch(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    public function delete(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    public function options(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(['OPTIONS'], $uri, $action);
    }

    public function any(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute($this->verbs, $uri, $action);
    }

    public function match(array $methods, string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(array_map('strtoupper', $methods), $uri, $action);
    }

    /**
     * Create a route group with shared attributes.
     */
    public function group(array $attributes, Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * Register a resource (CRUD) route.
     */
    public function resource(string $name, string $controller): void
    {
        $this->get("{$name}",           [$controller, 'index'])->name("{$name}.index");
        $this->get("{$name}/create",    [$controller, 'create'])->name("{$name}.create");
        $this->post("{$name}",          [$controller, 'store'])->name("{$name}.store");
        $this->get("{$name}/{id}",      [$controller, 'show'])->name("{$name}.show");
        $this->get("{$name}/{id}/edit", [$controller, 'edit'])->name("{$name}.edit");
        $this->put("{$name}/{id}",      [$controller, 'update'])->name("{$name}.update");
        $this->delete("{$name}/{id}",   [$controller, 'destroy'])->name("{$name}.destroy");
    }

    /**
     * Generate a URL for a named route.
     */
    public function url(string $name, array $parameters = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \InvalidArgumentException("Route [{$name}] not defined.");
        }

        $uri = $this->namedRoutes[$name];

        foreach ($parameters as $key => $value) {
            $uri = str_replace("{{$key}}", $value, $uri);
        }

        return '/' . ltrim($uri, '/');
    }

    /**
     * Dispatch the request to the matching route.
     */
    public function dispatch(string $method, string $uri): mixed
    {
        $uri    = '/' . trim($uri, '/');
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if ($route->matches($method, $uri)) {
                return $route->run();
            }
        }

        throw new \RuntimeException("No route matched [{$method}] {$uri}", 404);
    }

    /**
     * Get all registered routes.
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    protected function addRoute(array $methods, string $uri, Closure|array|string $action): Route
    {
        $uri   = $this->prefixUri($uri);
        $route = new Route($methods, $uri, $action);

        $this->routes[] = $route;

        return $route;
    }

    protected function prefixUri(string $uri): string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            $prefix .= '/' . trim($group['prefix'] ?? '', '/');
        }
        return trim($prefix . '/' . trim($uri, '/'), '/');
    }
}
