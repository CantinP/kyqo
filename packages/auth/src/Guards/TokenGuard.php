<?php

namespace Kyqo\Auth\Guards;

use Kyqo\Auth\GuardInterface;

/**
 * Token-based authentication guard.
 *
 * FIX SEC-5: id() now returns the authenticated user's ID instead of null.
 */
class TokenGuard implements GuardInterface
{
    protected string $name;
    protected array  $config;
    protected mixed  $user         = null;
    protected bool   $userResolved = false;

    public function __construct(string $name, array $config)
    {
        $this->name   = $name;
        $this->config = $config;
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

        $this->user = $this->retrieveByToken($token);
        return $this->user;
    }

    public function check(): bool { return $this->user() !== null; }

    /**
     * FIX SEC-5: Return the authenticated user's primary key, not null.
     */
    public function id(): mixed
    {
        $user = $this->user();
        if ($user === null) {
            return null;
        }
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
        if (is_string($inputToken) && strlen($inputToken) > 0) {
            return $inputToken;
        }

        return null;
    }

    protected function retrieveByToken(string $token): mixed
    {
        // Stub — implement via UserProvider
        return null;
    }
}
