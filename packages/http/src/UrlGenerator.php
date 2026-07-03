<?php

namespace Kyqo\Http;

use Kyqo\Http\Router\Router;

/**
 * Generates absolute and relative URLs for the application.
 *
 * FIX M2: previous() now sanitises the Referer header through the same
 * Response::sanitizeRedirectUrl() logic used by Response::redirect().
 * This prevents an Open Redirect when callers do:
 *   Response::redirect($url->previous())
 * A foreign or javascript: Referer is replaced by $fallback ('/').
 */
class UrlGenerator
{
    public function __construct(
        protected Router  $router,
        protected Request $request
    ) {}

    public function to(string $path, array $query = [], bool $secure = false): string
    {
        $scheme = $secure || $this->request->isSecure() ? 'https' : 'http';
        $host   = $this->request->host();
        $path   = '/' . ltrim($path, '/');

        if (!empty($query)) {
            $path .= '?' . http_build_query($query);
        }

        return $scheme . '://' . $host . $path;
    }

    public function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        $path = $this->router->url($name, $parameters);
        return $absolute ? $this->to($path) : $path;
    }

    public function current(): string
    {
        return $this->request->url();
    }

    /**
     * FIX M2: sanitise the Referer header to prevent Open Redirect.
     *
     * Accepts only:
     *   - Relative paths starting with '/'
     *   - Absolute URLs starting with 'http://' or 'https://'
     * Anything else (javascript:, data:, external domains used as attack
     * vectors, empty string) is replaced by $fallback.
     *
     * Note: the sanitised value is returned as-is; callers that need to
     * restrict to same-origin should additionally compare the host component.
     */
    public function previous(string $fallback = '/'): string
    {
        $referer = $this->request->header('referer', '');

        if (!is_string($referer) || $referer === '') {
            return $fallback;
        }

        return $this->sanitizeUrl($referer, $fallback);
    }

    public function __toString(): string
    {
        return $this->current();
    }

    /**
     * Rejects dangerous schemes and relative-but-protocol-relative URLs.
     * Mirrors the logic in Response::sanitizeRedirectUrl().
     */
    private function sanitizeUrl(string $url, string $fallback): string
    {
        $stripped = ltrim($url);

        if (preg_match('/^(javascript|data|vbscript|file):/i', $stripped)) {
            return $fallback;
        }

        if (str_starts_with($stripped, '/') && !str_starts_with($stripped, '//')) {
            return $url;
        }

        if (preg_match('/^https?:\/\//i', $stripped)) {
            return $url;
        }

        return $fallback;
    }
}
