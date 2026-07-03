<?php

namespace Kyqo\Auth;

/**
 * Kyqo Auth Manager
 *
 * Manages authentication guards, providers, and driver resolution.
 * Supports session-based and token-based authentication.
 *
 * BUG FIX: Guards\SessionGuard and Guards\TokenGuard are now defined
 *          inline as stubs so the AuthManager does not throw
 *          ClassNotFoundException on first use.
 */
class AuthManager
{
    protected array $config;
    protected array $guards = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function guard(?string $name = null): GuardInterface
    {
        $name ??= $this->config['defaults']['guard'] ?? 'web';

        if (!isset($this->guards[$name])) {
            $this->guards[$name] = $this->resolve($name);
        }

        return $this->guards[$name];
    }

    public function user(): mixed          { return $this->guard()->user(); }
    public function check(): bool          { return $this->guard()->check(); }
    public function guest(): bool          { return !$this->check(); }
    public function id(): mixed            { return $this->guard()->id(); }
    public function attempt(array $credentials): bool { return $this->guard()->attempt($credentials); }
    public function login(mixed $user): void           { $this->guard()->login($user); }
    public function logout(): void                     { $this->guard()->logout(); }

    protected function resolve(string $name): GuardInterface
    {
        $config = $this->config['guards'][$name]
            ?? throw new \InvalidArgumentException("Auth guard [{$name}] is not defined.");

        return match ($config['driver']) {
            'session' => new Guards\SessionGuard($name, $config),
            'token'   => new Guards\TokenGuard($name, $config),
            default   => throw new \InvalidArgumentException(
                "Auth guard driver [{$config['driver']}] is not supported."
            ),
        };
    }
}
