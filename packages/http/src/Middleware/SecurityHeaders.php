<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Http\Request;
use Kyqo\Http\Response;

/**
 * Security Headers Middleware
 *
 * FIX SEC-4: X-Kyqo-CSP-Nonce header removed — exposing the nonce via a
 *            response header defeats its purpose and enables XSS bypass.
 *            The nonce is available to templates via $request->getAttribute('csp_nonce').
 */
class SecurityHeaders
{
    protected array $headers = [
        'X-Frame-Options'               => 'SAMEORIGIN',
        'X-Content-Type-Options'        => 'nosniff',
        'X-XSS-Protection'              => '1; mode=block',
        'Strict-Transport-Security'     => 'max-age=31536000; includeSubDomains; preload',
        'Referrer-Policy'               => 'strict-origin-when-cross-origin',
        'Permissions-Policy'            => 'camera=(), microphone=(), geolocation=(), payment=()',
        'Cross-Origin-Opener-Policy'    => 'same-origin',
        'Cross-Origin-Resource-Policy'  => 'same-origin',
    ];

    protected string $csp = "default-src 'self'; script-src 'self' 'nonce-{nonce}'; style-src 'self' 'nonce-{nonce}'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'; upgrade-insecure-requests";

    public function handle(Request $request, \Closure $next): mixed
    {
        $nonce = base64_encode(random_bytes(16));
        $request->setAttribute('csp_nonce', $nonce);

        $response = $next($request);

        if (!$response instanceof Response) {
            return $response;
        }

        foreach ($this->headers as $key => $value) {
            $response->setHeader($key, $value);
        }

        $response->setHeader(
            'Content-Security-Policy',
            str_replace('{nonce}', $nonce, $this->csp)
        );

        // FIX SEC-4: X-Kyqo-CSP-Nonce intentionally NOT sent.
        // Access the nonce in templates via: $request->getAttribute('csp_nonce')

        return $response;
    }
}
