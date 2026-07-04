<?php

namespace Kyqo\Auth\Guards;

use Kyqo\Auth\GuardInterface;
use Kyqo\Auth\UserProviderInterface;
use Kyqo\Http\Request;

/**
 * Token-based authentication guard.
 *
 * FIX #2 (maintained): Token extraction delegates to the Request object.
 *
 * FIX AUDIT-5: Token expiry support.
 *   - If the resolved user row/object has a non-null `token_expires_at`
 *     field whose value is in the past, the user is treated as unauthenticated.
 *   - The column name is configurable via config key `expires_at_column`
 *     (default: 'token_expires_at').
 *   - login() accepts an optional $expiresAt (Unix timestamp or DateTimeInterface)
 *     and stores it so the guard can enforce it within the same request.
 */
class TokenGuard implements GuardInterface
{
    protected string  $name;
    protected array   $config;
    protected mixed   $user         = null;
    protected bool    $userResolved = false;
    protected ?int    $expiresAt    = null;
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
            return $this->isTokenExpired() ? null : $this->user;
        }

        $this->userResolved = true;
        $token = $this->extractToken();
        if ($token === null) {
            return null;
        }

        $user = $this->provider?->retrieveByToken($token);
        if ($user === null) {
            return null;
        }

        // FIX AUDIT-5: read expiry from user record.
        $expiresCol = $this->config['expires_at_column'] ?? 'token_expires_at';
        $rawExpiry  = is_array($user)
            ? ($user[$expiresCol] ?? null)
            : ($user->{$expiresCol} ?? null);

        if ($rawExpiry !== null) {
            $this->expiresAt = is_numeric($rawExpiry)
                ? (int) $rawExpiry
                : (int) strtotime((string) $rawExpiry);
        }

        if ($this->isTokenExpired()) {
            $this->user = null;
            return null;
        }

        $this->user = $user;
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

    /**
     * FIX AUDIT-5: login() accepts an optional expiry timestamp.
     *
     * @param mixed                        $user      User object or array.
     * @param \DateTimeInterface|int|null  $expiresAt Unix timestamp or DateTimeInterface; null = no expiry.
     */
    public function login(mixed $user, \DateTimeInterface|int|null $expiresAt = null): void
    {
        $this->user         = $user;
        $this->userResolved = true;

        if ($expiresAt instanceof \DateTimeInterface) {
            $this->expiresAt = $expiresAt->getTimestamp();
        } elseif (is_int($expiresAt)) {
            $this->expiresAt = $expiresAt;
        } else {
            $this->expiresAt = null;
        }
    }

    public function logout(): void
    {
        $this->user         = null;
        $this->userResolved = false;
        $this->expiresAt    = null;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * FIX AUDIT-5: returns true when a non-null expiry is set and has passed.
     */
    protected function isTokenExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < time();
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
