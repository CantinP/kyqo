<?php

namespace Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Kyqo\Auth\Guards\SessionGuard;
use Kyqo\Auth\UserProviderInterface;
use Kyqo\Http\Request;

class SessionGuardTest extends TestCase
{
    private function makeProvider(?object $user = null): UserProviderInterface
    {
        $provider = $this->createMock(UserProviderInterface::class);
        $provider->method('retrieveById')->willReturn($user);
        $provider->method('retrieveByCredentials')->willReturn($user);
        $provider->method('validateCredentials')->willReturn($user !== null);
        return $provider;
    }

    private function makeRequest(array $session = []): Request
    {
        $_SESSION = $session;
        $request = $this->createMock(Request::class);
        return $request;
    }

    public function test_guest_when_no_session(): void
    {
        $guard = new SessionGuard(
            $this->makeProvider(null),
            $this->makeRequest(),
            'web'
        );

        $this->assertFalse($guard->check());
        $this->assertTrue($guard->guest());
        $this->assertNull($guard->user());
    }

    public function test_check_returns_true_when_user_exists(): void
    {
        $user = (object) ['id' => 1, 'email' => 'test@example.com'];
        $guard = new SessionGuard(
            $this->makeProvider($user),
            $this->makeRequest(['_auth_web_id' => 1]),
            'web'
        );

        $this->assertTrue($guard->check());
        $this->assertFalse($guard->guest());
    }

    public function test_id_returns_user_id(): void
    {
        $user = (object) ['id' => 42, 'email' => 'john@example.com'];
        $guard = new SessionGuard(
            $this->makeProvider($user),
            $this->makeRequest(['_auth_web_id' => 42]),
            'web'
        );

        $this->assertSame(42, $guard->id());
    }

    public function test_login_sets_user(): void
    {
        $user     = (object) ['id' => 7, 'email' => 'login@example.com'];
        $provider = $this->makeProvider($user);
        $guard    = new SessionGuard($provider, $this->makeRequest(), 'web');

        $guard->login($user);

        $this->assertTrue($guard->check());
        $this->assertSame($user, $guard->user());
    }

    public function test_logout_clears_user(): void
    {
        $user  = (object) ['id' => 3, 'email' => 'bye@example.com'];
        $guard = new SessionGuard(
            $this->makeProvider($user),
            $this->makeRequest(['_auth_web_id' => 3]),
            'web'
        );

        $guard->logout();

        $this->assertFalse($guard->check());
        $this->assertNull($guard->user());
    }

    public function test_attempt_fails_with_invalid_credentials(): void
    {
        $provider = $this->createMock(UserProviderInterface::class);
        $provider->method('retrieveByCredentials')->willReturn(null);
        $provider->method('validateCredentials')->willReturn(false);

        $guard = new SessionGuard($provider, $this->makeRequest(), 'web');

        $this->assertFalse($guard->attempt(['email' => 'bad@example.com', 'password' => 'wrong']));
        $this->assertFalse($guard->check());
    }

    public function test_attempt_succeeds_with_valid_credentials(): void
    {
        $user     = (object) ['id' => 5, 'email' => 'ok@example.com'];
        $provider = $this->createMock(UserProviderInterface::class);
        $provider->method('retrieveByCredentials')->willReturn($user);
        $provider->method('validateCredentials')->willReturn(true);

        $guard = new SessionGuard($provider, $this->makeRequest(), 'web');

        $this->assertTrue($guard->attempt(['email' => 'ok@example.com', 'password' => 'secret']));
        $this->assertTrue($guard->check());
    }
}
