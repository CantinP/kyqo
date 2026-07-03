<?php

namespace Kyqo\Http;

use Kyqo\Http\Router\Router;

/**
 * Generates absolute and relative URLs for the application.
 *
 * Bound as 'url' in the container — satisfies helpers.php `route()` and `url()`.
 */
class UrlGenerator
{
    public function __construct(
        protected Router  $router,
        protected Request $request
    ) {}

    /**
     * Generate an absolute URL for the given path.
     */
    public function to(string $path, array $query = [], bool $secure = false): string
    {
        $scheme = $secure || $this->request->isSecure() ? 'https' : 'http';
        $host   = $this->request->server('HTTP_HOST', 'localhost');
        $path   = '/' . ltrim($path, '/');

        if (!empty($query)) {
            $path .= '?' . http_build_query($query);
        }

        return $scheme . '://' . $host . $path;
    }

    /**
     * Generate a URL for a named route.
     * Satisfies helpers.php `route(name, params, absolute)` call.
     */
    public function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        $path = $this->router->url($name, $parameters);
        return $absolute ? $this->to($path) : $path;
    }

    /**
     * Get the current full URL.
     */
    public function current(): string
    {
        return $this->request->url();
    }

    /**
     * Get the previous URL (from Referer header, fallback to '/').
     */
    public function previous(string $fallback = '/'): string
    {
        return $this->request->header('referer', $fallback);
    }

    public function __toString(): string
    {
        return $this->current();
    }
}
