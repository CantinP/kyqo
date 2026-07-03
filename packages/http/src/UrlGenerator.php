<?php

namespace Kyqo\Http;

use Kyqo\Http\Router\Router;

/**
 * Generates absolute and relative URLs for the application.
 *
 * FIX N5: to() now uses Request::getValidatedHost() (regex-validated)
 * instead of the raw HTTP_HOST server var, preventing Host Header injection
 * in generated URLs (emails, redirects, etc.).
 */
class UrlGenerator
{
    public function __construct(
        protected Router  $router,
        protected Request $request
    ) {}

    /**
     * Generate an absolute URL for the given path.
     *
     * FIX N5: host is obtained via the validated accessor, not raw server().
     */
    public function to(string $path, array $query = [], bool $secure = false): string
    {
        $scheme = $secure || $this->request->isSecure() ? 'https' : 'http';
        $host   = $this->request->host();   // validated host (see Request::host())
        $path   = '/' . ltrim($path, '/');

        if (!empty($query)) {
            $path .= '?' . http_build_query($query);
        }

        return $scheme . '://' . $host . $path;
    }

    /**
     * Generate a URL for a named route.
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
