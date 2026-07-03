<?php

namespace Kyqo\Auth\Guards;

use Kyqo\Auth\GuardInterface;

/**
 * Session-based authentication guard.
 *
 * Authenticates users via PHP session storage.
 * Passwords are verified with password_verify() (bcrypt/argon2).
 *
 * SECURITY:
 * - Session is regenerated on login to prevent session fixation.
 * - Password comparison uses password_verify() — never plain comparison.
 * - User ID stored in session, not the full user object.
 */
class SessionGuard implements GuardInterface
{
    protected string $name;
    protected array $config;
    protected mixed $user = null;
    protected bool $userResolved = false;

    protected string $sessionKey;

    public function __construct(string $name, array $config)
    {
        $this->name       = $name;
        $this->config     = $config;
        $this->sessionKey = '_kyqo_auth_' . $name;
    }

    /**
     * Get the currently authenticated user.
     */
    public function user(): mixed
    {
        if ($this->userResolved) {
            return $this->user;
        }

        $this->userResolved = true;

        $id = $_SESSION[$this->sessionKey] ?? null;
        if ($id === null) {
            return null;
        }

        $this->user = $this->retrieveById($id);
        return $this->user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): mixed
    {
        return $_SESSION[$this->sessionKey] ?? null;
    }

    /**
     * Attempt to authenticate with credentials.
     *
     * SECURITY: Uses password_verify() for safe hash comparison.
     * SECURITY: Session is regenerated on successful login (prevents fixation).
     */
    public function attempt(array $credentials): bool
    {
        $user = $this->retrieveByCredentials($credentials);

        if ($user === null) {
            return false;
        }

        $passwordField = $this->config['password_field'] ?? 'password';
        $plain         = $credentials[$passwordField] ?? '';
        $hashed        = is_array($user) ? ($user[$passwordField] ?? '') : ($user->$passwordField ?? '');

        if (!password_verify($plain, $hashed)) {
            return false;
        }

        $this->login($user);
        return true;
    }

    /**
     * Log a user in.
     * SECURITY: Regenerates session ID to prevent session fixation attacks.
     */
    public function login(mixed $user): void
    {
        $id = is_array($user) ? ($user['id'] ?? null) : ($user->id ?? null);

        // Regenerate session ID on login — prevents session fixation
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION[$this->sessionKey] = $id;
        $this->user         = $user;
        $this->userResolved = true;
    }

    /**
     * Log the current user out.
     */
    public function logout(): void
    {
        unset($_SESSION[$this->sessionKey]);
        $this->user         = null;
        $this->userResolved = false;

        // Regenerate session ID on logout to invalidate old session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Retrieve a user by their ID.
     * Override in your application by binding a UserProvider.
     */
    protected function retrieveById(mixed $id): mixed
    {
        // Stub — to be implemented via UserProvider in a future commit
        return null;
    }

    /**
     * Retrieve a user by credentials (e.g. email).
     * Override in your application by binding a UserProvider.
     */
    protected function retrieveByCredentials(array $credentials): mixed
    {
        // Stub — to be implemented via UserProvider in a future commit
        return null;
    }
}
