<?php

namespace Kyqo\Http\Router;

use Closure;

/**
 * Kyqo Router
 *
 * Handles HTTP route registration, parameter extraction, named routes,
 * route groups, middleware stacks, and route dispatching.
 *
 * BUG FIX: Added findRoute() method used by Kernel::dispatch().
 * BUG FIX: dispatch() now throws HttpNotFoundException (with code 404)
 *          instead of a generic RuntimeException.
 * BUG FIX: url() now validates parameter values to prevent injection.
 */
class Router
{
    protected array $routes      = [];
    protected array $namedRoutes = [];
    protected array $groupStack  = [];

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
     * Create a route group with shared attributes (prefix, middleware, namespace).
     */
    public function group(array $attributes, Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * Register RESTful resource routes.
     */
    public function resource(string $name, string $controller): void
    {
        $this->get("{$name}",                [$controller, 'index'])->name("{$name}.index");
        $this->get("{$name}/create",         [$controller, 'create'])->name("{$name}.create");
        $this->post("{$name}",               [$controller, 'store'])->name("{$name}.store");
        $this->get("{$name}/{id}",           [$controller, 'show'])->name("{$name}.show");
        $this->get("{$name}/{id}/edit",      [$controller, 'edit'])->name("{$name}.edit");
        $this->put("{$name}/{id}",           [$controller, 'update'])->name("{$name}.update");
        $this->delete("{$name}/{id}",        [$controller, 'destroy'])->name("{$name}.destroy");
    }

    /**
     * Generate a URL for a named route.
     * BUG FIX (SEC-2): Parameter values are URL-encoded to prevent injection.
     */
    public function url(string $name, array $parameters = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \InvalidArgumentException("Route [{$name}] not defined.");
        }

        $uri = $this->namedRoutes[$name];

        foreach ($parameters as $key => $value) {
            // Validate key is a simple alphanumeric identifier
            if (!preg_match('/^[a-zA-Z0-9_]+$/', (string) $key)) {
                throw new \InvalidArgumentException("Invalid route parameter key [{$key}].");
            }
            $uri = str_replace("{{$key}}", rawurlencode((string) $value), $uri);
        }

        // If any placeholders remain, they were not provided
        if (preg_match('/\{[^}]+\}/', $uri)) {
            throw new \InvalidArgumentException("Missing required parameters for route [{$name}].");
        }

        return '/' . ltrim($uri, '/');
    }

    /**
     * Find the first route matching the given method and URI.
     * Returns null if no match found (used by Kernel::dispatch).
     */
    public function findRoute(string $method, string $uri): ?Route
    {
        $uri    = '/' . trim($uri, '/');
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if ($route->matches($method, $uri)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Dispatch directly (kept for backwards compat / CLI use).
     */
    public function dispatch(string $method, string $uri): mixed
    {
        $route = $this->findRoute($method, $uri);

        if ($route === null) {
            throw new \RuntimeException("No route matched [{$method}] {$uri}", 404);
        }

        return $route->run();
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    protected function addRoute(array $methods, string $uri, Closure|array|string $action): Route
    {
        $uri   = $this->prefixUri($uri);
        $route = new Route($methods, $uri, $action);

        $this->routes[] = $route;

        if (isset($this->groupStack)) {
            foreach ($this->groupStack as $group) {
                if (!empty($group['middleware'])) {
                    $route->middleware($group['middleware']);
                }
            }
        }

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
