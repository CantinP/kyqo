<?php

namespace Kyqo\Auth\Guards;

use Kyqo\Auth\GuardInterface;
use Kyqo\Auth\UserProviderInterface;

/**
 * Session-based authentication guard.
 *
 * MINOR-V4-4: Now accepts an optional UserProviderInterface so that
 * retrieveById() and retrieveByCredentials() are not permanent stubs.
 *
 * SECURITY:
 * - Session is regenerated on login to prevent session fixation.
 * - Password comparison uses password_verify().
 * - User ID stored in session, not the full object.
 */
class SessionGuard implements GuardInterface
{
    protected string $name;
    protected array  $config;
    protected mixed  $user         = null;
    protected bool   $userResolved = false;
    protected string $sessionKey;
    protected ?UserProviderInterface $provider;

    public function __construct(
        string $name,
        array $config,
        ?UserProviderInterface $provider = null
    ) {
        $this->name       = $name;
        $this->config     = $config;
        $this->sessionKey = '_kyqo_auth_' . $name;
        $this->provider   = $provider;
    }

    public function user(): mixed
    {
        if ($this->userResolved) {
            return $this->user;
        }
        $this->userResolved = true;
        $id = $this->sessionId();
        if ($id === null) {
            return null;
        }
        $this->user = $this->retrieveById($id);
        return $this->user;
    }

    public function check(): bool { return $this->user() !== null; }

    public function id(): mixed { return $this->sessionId(); }

    public function attempt(array $credentials): bool
    {
        $user = $this->retrieveByCredentials($credentials);
        if ($user === null) {
            return false;
        }

        $passwordField = $this->provider?->passwordField()
            ?? ($this->config['password_field'] ?? 'password');
        $plain  = $credentials[$passwordField] ?? '';
        $hashed = is_array($user) ? ($user[$passwordField] ?? '') : ($user->$passwordField ?? '');

        if (!password_verify($plain, $hashed)) {
            return false;
        }

        $this->login($user);
        return true;
    }

    public function login(mixed $user): void
    {
        $id = is_array($user) ? ($user['id'] ?? null) : ($user->id ?? null);

        $this->guardSession();
        session_regenerate_id(true);

        $_SESSION[$this->sessionKey] = $id;
        $this->user         = $user;
        $this->userResolved = true;
    }

    public function logout(): void
    {
        unset($_SESSION[$this->sessionKey]);
        $this->user         = null;
        $this->userResolved = false;

        $this->guardSession();
        session_regenerate_id(true);
    }

    // ---- Helpers ------------------------------------------------------------

    protected function sessionId(): mixed
    {
        $this->guardSession();
        return $_SESSION[$this->sessionKey] ?? null;
    }

    /**
     * SEC-V4-1 FIX: Replace @session_start() with a clean status check.
     * Throws only if something truly unexpected happens.
     */
    protected function guardSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // PHP_SESSION_DISABLED would be a server mis-configuration — let it surface naturally.
    }

    protected function retrieveById(mixed $id): mixed
    {
        return $this->provider?->retrieveById($id);
    }

    protected function retrieveByCredentials(array $credentials): mixed
    {
        return $this->provider?->retrieveByCredentials($credentials);
    }
}
