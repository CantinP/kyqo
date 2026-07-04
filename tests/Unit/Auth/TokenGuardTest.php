<?php

namespace Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Kyqo\Auth\Guards\TokenGuard;
use Kyqo\Auth\UserProviderInterface;
use Kyqo\Http\Request;

class TokenGuardTest extends TestCase
{
    private function makeRequest(string $token = '', string $method = 'header'): Request
    {
        $request = $this->createMock(Request::class);

        if ($method === 'header') {
            $request->method('bearerToken')->willReturn($token ?: null);
            $request->method('query')->willReturn(null);
        } else {
            $request->method('bearerToken')->willReturn(null);
            $request->method('query')->with('api_token')->willReturn($token ?: null);
        }

        return $request;
    }

    private function makeProvider(?object $user = null): UserProviderInterface
    {
        $provider = $this->createMock(UserProviderInterface::class);
        $provider->method('retrieveByToken')->willReturn($user);
        return $provider;
    }

    public function test_guest_when_no_token(): void
    {
        $guard = new TokenGuard(
            $this->makeProvider(null),
            $this->makeRequest(''),
            'api'
        );

        $this->assertFalse($guard->check());
        $this->assertTrue($guard->guest());
        $this->assertNull($guard->user());
    }

    public function test_check_when_valid_token(): void
    {
        $user  = (object) ['id' => 1, 'api_token' => 'abc123'];
        $guard = new TokenGuard(
            $this->makeProvider($user),
            $this->makeRequest('abc123'),
            'api'
        );

        $this->assertTrue($guard->check());
        $this->assertSame($user, $guard->user());
    }

    public function test_guest_when_token_invalid(): void
    {
        $guard = new TokenGuard(
            $this->makeProvider(null),
            $this->makeRequest('invalid-token'),
            'api'
        );

        $this->assertFalse($guard->check());
    }

    public function test_id_returns_null_for_guest(): void
    {
        $guard = new TokenGuard(
            $this->makeProvider(null),
            $this->makeRequest(''),
            'api'
        );

        $this->assertNull($guard->id());
    }

    public function test_id_returns_user_id_when_authenticated(): void
    {
        $user  = (object) ['id' => 99, 'api_token' => 'mytoken'];
        $guard = new TokenGuard(
            $this->makeProvider($user),
            $this->makeRequest('mytoken'),
            'api'
        );

        $this->assertSame(99, $guard->id());
    }
}
