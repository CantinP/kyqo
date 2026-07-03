<?php

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use Kyqo\Http\Middleware\ThrottleRequests;
use Kyqo\Http\Request;
use Kyqo\Http\Response;

class ThrottleTest extends TestCase
{
    public function test_requests_within_limit_pass(): void
    {
        $middleware = new ThrottleRequests(5, 1);
        $request    = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test-throttle-ok', 'REMOTE_ADDR' => '127.0.0.1']);

        $response = $middleware->handle($request, fn ($r) => Response::make('ok'));

        $this->assertSame(200, $response->status());
        $this->assertNotNull($response->getHeader('X-RateLimit-Limit'));
        $this->assertNotNull($response->getHeader('X-RateLimit-Remaining'));
    }

    public function test_exceeding_limit_returns_429(): void
    {
        $middleware = new ThrottleRequests(2, 1);
        $request    = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test-throttle-429', 'REMOTE_ADDR' => '10.0.0.99']);

        // Exhaust the limit
        $middleware->handle($request, fn ($r) => Response::make('ok'));
        $middleware->handle($request, fn ($r) => Response::make('ok'));
        $response = $middleware->handle($request, fn ($r) => Response::make('ok'));

        $this->assertSame(429, $response->status());
        $this->assertNotNull($response->getHeader('Retry-After'));
        $this->assertSame('0', $response->getHeader('X-RateLimit-Remaining'));
    }
}
