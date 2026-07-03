<?php

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use Kyqo\Http\Middleware\VerifyCsrfToken;
use Kyqo\Http\Request;
use Kyqo\Http\Response;

class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['_kyqo_csrf_token']);
    }

    public function test_get_request_bypasses_csrf(): void
    {
        $request  = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
        $middleware = new VerifyCsrfToken();
        $called   = false;

        $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return Response::make('ok');
        });

        $this->assertTrue($called);
    }

    public function test_post_without_token_returns_419(): void
    {
        $request    = new Request([], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/form']);
        $middleware = new VerifyCsrfToken();

        $response = $middleware->handle($request, fn ($r) => Response::make('ok'));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(419, $response->status());
    }

    public function test_post_with_valid_token_passes(): void
    {
        $token = VerifyCsrfToken::generateToken();
        $_SESSION['_kyqo_csrf_token'] = $token;

        $request    = new Request(['_token' => $token], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/form']);
        $middleware = new VerifyCsrfToken();
        $called     = false;

        $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return Response::make('ok');
        });

        $this->assertTrue($called);
    }

    public function test_post_with_wrong_token_is_rejected(): void
    {
        $_SESSION['_kyqo_csrf_token'] = VerifyCsrfToken::generateToken();

        $request    = new Request(['_token' => 'wrong_token'], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/form']);
        $middleware = new VerifyCsrfToken();

        $response = $middleware->handle($request, fn ($r) => Response::make('ok'));

        $this->assertSame(419, $response->status());
    }

    public function test_api_routes_are_exempt_from_csrf(): void
    {
        $request    = new Request([], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/users']);
        $middleware = new VerifyCsrfToken();
        $called     = false;

        $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return Response::make('ok');
        });

        $this->assertTrue($called);
    }

    public function test_token_has_minimum_entropy(): void
    {
        $token = VerifyCsrfToken::generateToken();
        $this->assertGreaterThanOrEqual(64, strlen($token), 'CSRF token must be at least 64 hex chars (32 bytes)');
    }
}
