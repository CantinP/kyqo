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
 *
 * FIX AUDIT-8: Aligned X-Frame-Options with CSP frame-ancestors.
 *              Previously X-Frame-Options was 'SAMEORIGIN' while
 *              frame-ancestors was 'none' — contradictory policies.
 *              Both are now 'DENY' / 'none' (most restrictive; change to
 *              SAMEORIGIN + 'self' together if embedding is required).
 */
class SecurityHeaders
{
    protected array $headers = [
        // FIX AUDIT-8: DENY aligns with frame-ancestors 'none' in the CSP below.
        'X-Frame-Options'               => 'DENY',
        'X-Content-Type-Options'        => 'nosniff',
        'X-XSS-Protection'              => '1; mode=block',
        'Strict-Transport-Security'     => 'max-age=31536000; includeSubDomains; preload',
        'Referrer-Policy'               => 'strict-origin-when-cross-origin',
        'Permissions-Policy'            => 'camera=(), microphone=(), geolocation=(), payment=()',
        'Cross-Origin-Opener-Policy'    => 'same-origin',
        'Cross-Origin-Resource-Policy'  => 'same-origin',
    ];

    /**
     * CSP template.
     *
     * frame-ancestors 'none': no framing allowed from any origin.
     * This must be consistent with X-Frame-Options above.
     * To allow same-origin framing change both to 'self' / 'SAMEORIGIN'.
     */
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
