<?php

namespace Kyqo\Auth\Guards;

use Kyqo\Auth\GuardInterface;
use Kyqo\Auth\UserProviderInterface;
use Kyqo\Auth\Providers\DatabaseUserProvider;

/**
 * Session-based authentication guard.
 *
 * FIX N7: login() now optionally sets a remember-me cookie by storing
 * a signed token in the `remember_token` column and issuing an HttpOnly
 * Secure cookie. On subsequent requests, user() will check the cookie
 * when no session ID is present.
 *
 * Remember-me is opt-in via $remember = true in attempt() / login().
 */
class SessionGuard implements GuardInterface
{
    protected string $name;
    protected array  $config;
    protected mixed  $user         = null;
    protected bool   $userResolved = false;
    protected string $sessionKey;
    protected string $cookieName;
    protected ?UserProviderInterface $provider;

    public function __construct(
        string $name,
        array $config,
        ?UserProviderInterface $provider = null
    ) {
        $this->name       = $name;
        $this->config     = $config;
        $this->sessionKey = '_kyqo_auth_' . $name;
        $this->cookieName = 'remember_' . $name . '_' . md5(static::class);
        $this->provider   = $provider;
    }

    public function user(): mixed
    {
        if ($this->userResolved) {
            return $this->user;
        }
        $this->userResolved = true;

        // 1. Session-based lookup
        $id = $this->sessionId();
        if ($id !== null) {
            $this->user = $this->retrieveById($id);
            return $this->user;
        }

        // 2. FIX N7: Remember-me cookie fallback
        $this->user = $this->recallFromCookie();
        return $this->user;
    }

    public function check(): bool { return $this->user() !== null; }

    public function id(): mixed { return $this->sessionId(); }

    public function attempt(array $credentials, bool $remember = false): bool
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

        $this->login($user, $remember);
        return true;
    }

    /**
     * FIX N7: login() accepts an optional $remember flag.
     * When true and a DatabaseUserProvider is available, it stores a
     * hashed random token and queues an HttpOnly cookie for 30 days.
     */
    public function login(mixed $user, bool $remember = false): void
    {
        $id = is_array($user) ? ($user['id'] ?? null) : ($user->id ?? null);

        $this->guardSession();
        session_regenerate_id(true);

        $_SESSION[$this->sessionKey] = $id;
        $this->user         = $user;
        $this->userResolved = true;

        if ($remember && $this->provider instanceof DatabaseUserProvider) {
            $rawToken = bin2hex(random_bytes(40));
            $this->provider->updateRememberToken($user, $rawToken);
            $this->queueRememberCookie($rawToken);
        }
    }

    public function logout(): void
    {
        $this->guardSession();

        unset($_SESSION[$this->sessionKey]);
        $this->user         = null;
        $this->userResolved = false;

        session_regenerate_id(true);

        // Expire the remember-me cookie
        $this->expireRememberCookie();
    }

    // ---- Remember-me helpers ------------------------------------------------

    /**
     * FIX N7: attempt to authenticate from a remember-me cookie.
     */
    protected function recallFromCookie(): mixed
    {
        $token = $_COOKIE[$this->cookieName] ?? null;
        if (!is_string($token) || $token === '') {
            return null;
        }

        // Only DatabaseUserProvider supports remember-me token lookup
        if (!$this->provider instanceof DatabaseUserProvider) {
            return null;
        }

        [$userId, $rawToken] = explode('|', $token, 2) + [null, null];
        if ($userId === null || $rawToken === null) {
            return null;
        }

        $user = $this->provider->verifyRememberToken((int) $userId, $rawToken);
        if ($user !== null) {
            // Re-stamp session so next request skips cookie
            $this->guardSession();
            $_SESSION[$this->sessionKey] = $userId;
            $this->user         = $user;
            $this->userResolved = true;
            // Refresh token rotation
            $rawNew = bin2hex(random_bytes(40));
            $this->provider->updateRememberToken($user, $rawNew);
            $this->queueRememberCookie($rawNew);
        }
        return $user;
    }

    protected function queueRememberCookie(string $rawToken): void
    {
        $id    = is_array($this->user) ? ($this->user['id'] ?? 0) : ($this->user->id ?? 0);
        $value = $id . '|' . $rawToken;
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie(
            $this->cookieName,
            $value,
            [
                'expires'  => time() + 60 * 60 * 24 * 30,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => $secure,
            ]
        );
    }

    protected function expireRememberCookie(): void
    {
        setcookie($this->cookieName, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    // ---- Session helpers ----------------------------------------------------

    protected function sessionId(): mixed
    {
        $this->guardSession();
        return $_SESSION[$this->sessionKey] ?? null;
    }

    protected function guardSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
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
