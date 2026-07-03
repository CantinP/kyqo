<?php

namespace Kyqo\Auth\Guards;

use Kyqo\Auth\GuardInterface;
use Kyqo\Auth\UserProviderInterface;
use Kyqo\Http\Request;

/**
 * Token-based authentication guard.
 *
 * FIX #2: Token extraction now delegates to the Request object
 * instead of reading $_SERVER / $_GET / $_POST directly.
 * An optional Request is accepted; falls back to Request::capture()
 * only when the guard is used outside the HTTP cycle (e.g. CLI tests).
 */
class TokenGuard implements GuardInterface
{
    protected string  $name;
    protected array   $config;
    protected mixed   $user         = null;
    protected bool    $userResolved = false;
    protected ?UserProviderInterface $provider;
    protected ?Request $request;

    public function __construct(
        string $name,
        array $config,
        ?UserProviderInterface $provider = null,
        ?Request $request = null
    ) {
        $this->name     = $name;
        $this->config   = $config;
        $this->provider = $provider;
        $this->request  = $request;
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

    /**
     * FIX #2: Use Request object for all header/input access.
     */
    protected function extractToken(): ?string
    {
        $req = $this->request ?? Request::capture();

        // 1. Authorization: Bearer <token>
        $bearer = $req->bearerToken();
        if ($bearer !== null) {
            return $bearer;
        }

        // 2. ?api_token= query/body param
        $inputToken = $req->get('api_token');
        if (is_string($inputToken) && $inputToken !== '') {
            return $inputToken;
        }

        return null;
    }
}
