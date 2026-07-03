<?php

namespace Kyqo\Http\Router;

use Closure;

class Router
{
    protected array $routes      = [];
    protected array $namedRoutes = [];
    protected array $groupStack  = [];

    protected array $verbs = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    // -------------------------------------------------------------------------
    // Route registration
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Groups & resources
    // -------------------------------------------------------------------------

    public function group(array $attributes, Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

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

    // -------------------------------------------------------------------------
    // URL generation
    // -------------------------------------------------------------------------

    public function url(string $name, array $parameters = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \InvalidArgumentException("Route [{$name}] not defined.");
        }

        $uri = $this->namedRoutes[$name];

        foreach ($parameters as $key => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', (string) $key)) {
                throw new \InvalidArgumentException("Invalid route parameter key [{$key}].");
            }
            $uri = str_replace("{{$key}}", rawurlencode((string) $value), $uri);
        }

        if (preg_match('/\{[^}]+\}/', $uri)) {
            throw new \InvalidArgumentException("Missing required parameters for route [{$name}].");
        }

        return '/' . ltrim($uri, '/');
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

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

    /**
     * Called by Route::name() to register the route URI under a name.
     * MINOR FIX: Named routes are now actually stored.
     */
    public function registerNamedRoute(string $name, string $uri): void
    {
        $this->namedRoutes[$name] = $uri;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    protected function addRoute(array $methods, string $uri, Closure|array|string $action): Route
    {
        $uri   = $this->prefixUri($uri);
        $route = (new Route($methods, $uri, $action))->setRouter($this);

        // Inherit group middleware
        foreach ($this->groupStack as $group) {
            if (!empty($group['middleware'])) {
                $route->middleware($group['middleware']);
            }
        }

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
