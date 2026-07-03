<?php

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use Kyqo\Http\Middleware\SecurityHeaders;
use Kyqo\Http\Request;
use Kyqo\Http\Response;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_added_to_response(): void
    {
        $middleware = new SecurityHeaders();
        $request    = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

        /** @var Response $response */
        $response = $middleware->handle($request, fn ($r) => Response::make('hello'));

        $this->assertSame('SAMEORIGIN', $response->getHeader('X-Frame-Options'));
        $this->assertSame('nosniff', $response->getHeader('X-Content-Type-Options'));
        $this->assertSame('1; mode=block', $response->getHeader('X-XSS-Protection'));
        $this->assertStringContainsString('max-age=31536000', $response->getHeader('Strict-Transport-Security', ''));
        $this->assertSame('strict-origin-when-cross-origin', $response->getHeader('Referrer-Policy'));
    }

    public function test_csp_header_contains_nonce(): void
    {
        $middleware = new SecurityHeaders();
        $request    = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

        $response = $middleware->handle($request, fn ($r) => Response::make('hello'));
        $csp      = $response->getHeader('Content-Security-Policy', '');

        $this->assertStringContainsString("nonce-", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
    }

    public function test_nonce_is_unique_per_request(): void
    {
        $middleware = new SecurityHeaders();
        $r1 = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
        $r2 = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

        $resp1 = $middleware->handle($r1, fn ($r) => Response::make(''));
        $resp2 = $middleware->handle($r2, fn ($r) => Response::make(''));

        $nonce1 = $resp1->getHeader('X-Kyqo-CSP-Nonce');
        $nonce2 = $resp2->getHeader('X-Kyqo-CSP-Nonce');

        $this->assertNotSame($nonce1, $nonce2);
    }
}
