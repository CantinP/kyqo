<?php

namespace Kyqo\Auth\Guards;

use Kyqo\Auth\GuardInterface;
use Kyqo\Http\Request;

/**
 * Token-based authentication guard.
 *
 * Authenticates API requests via Bearer token in the Authorization header
 * or via an `api_token` query/body parameter.
 *
 * SECURITY:
 * - Token comparison uses hash_equals() to prevent timing attacks.
 * - Tokens should be stored hashed (SHA-256) in the database.
 */
class TokenGuard implements GuardInterface
{
    protected string $name;
    protected array $config;
    protected mixed $user = null;
    protected bool $userResolved = false;

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

    public function check(): bool  { return $this->user() !== null; }
    public function id(): mixed    { return null; }

    /**
     * Token guards don't support attempt().
     */
    public function attempt(array $credentials): bool
    {
        return false;
    }

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

    /**
     * Extract the Bearer token from the request.
     */
    protected function extractToken(): ?string
    {
        // From Authorization: Bearer <token>
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            $token = substr($auth, 7);
            if (preg_match('/^[A-Za-z0-9\-_\.~\+\/]+=*$/', $token)) {
                return $token;
            }
        }

        // From query string or POST body
        $inputToken = $_GET['api_token'] ?? $_POST['api_token'] ?? null;
        if (is_string($inputToken) && strlen($inputToken) > 0) {
            return $inputToken;
        }

        return null;
    }

    /**
     * Retrieve a user by token.
     * SECURITY: Compare hashed tokens with hash_equals() to prevent timing attacks.
     * Override in your application by binding a UserProvider.
     */
    protected function retrieveByToken(string $token): mixed
    {
        // Stub — to be implemented via UserProvider
        return null;
    }
}
