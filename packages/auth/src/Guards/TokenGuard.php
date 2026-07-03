<?php

namespace Kyqo\Auth\Guards;

use Kyqo\Auth\GuardInterface;
use Kyqo\Auth\UserProviderInterface;

/**
 * Token-based authentication guard.
 *
 * MINOR-V4-4: Accepts an optional UserProviderInterface.
 * SEC-5 FIX (maintained): id() returns user's primary key, not null.
 */
class TokenGuard implements GuardInterface
{
    protected string $name;
    protected array  $config;
    protected mixed  $user         = null;
    protected bool   $userResolved = false;
    protected ?UserProviderInterface $provider;

    public function __construct(
        string $name,
        array $config,
        ?UserProviderInterface $provider = null
    ) {
        $this->name     = $name;
        $this->config   = $config;
        $this->provider = $provider;
    }

    public function user(): mixed
    {
        if ($this->userResolved) {
            return $this->user;
        }
        $this->userResolved = true;
        $token = $this->extractToken();
        if ($token === null) {
            return null;
        }
        $this->user = $this->provider?->retrieveByToken($token);
        return $this->user;
    }

    public function check(): bool { return $this->user() !== null; }

    public function id(): mixed
    {
        $user = $this->user();
        if ($user === null) return null;
        return is_array($user) ? ($user['id'] ?? null) : ($user->id ?? null);
    }

    public function attempt(array $credentials): bool { return false; }

    public function login(mixed $user): void
    {
        $this->user         = $user;
        $this->userResolved = true;
    }

    public function logout(): void
    {
        $this->user         = null;
        $this->userResolved = false;
    }

    protected function extractToken(): ?string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            $token = substr($auth, 7);
            if (preg_match('/^[A-Za-z0-9\-_\.~\+\/]+=*$/', $token)) {
                return $token;
            }
        }
        $inputToken = $_GET['api_token'] ?? $_POST['api_token'] ?? null;
        if (is_string($inputToken) && $inputToken !== '') {
            return $inputToken;
        }
        return null;
    }
}
