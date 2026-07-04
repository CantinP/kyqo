<?php

namespace Kyqo\Auth\Guards;

use Kyqo\Auth\GuardInterface;
use Kyqo\Auth\UserProviderInterface;
use Kyqo\Auth\Providers\DatabaseUserProvider;

/**
 * Session-based authentication guard.
 *
 * FIX C1: recallFromCookie() validates the cookie format with a strict
 * regex before any processing.
 *
 * FIX AUDIT-5: guardSession() now calls session_set_cookie_params() before
 * session_start() to ensure the PHP session cookie is sent with
 * HttpOnly, SameSite=Lax, and Secure (when on HTTPS) attributes.
 * This prevents session hijacking via XSS and CSRF.
 *
 * Pattern: "<digits>|<80 hex chars>"  (id|sha256(rawToken) = 10+1+80 chars)
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

    /** Expected cookie format: "<int>|<80 lower-hex chars>" */
    private const REMEMBER_TOKEN_PATTERN = '/^\d+\|[a-f0-9]{80}$/';

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

        $id = $this->sessionId();
        if ($id !== null) {
            $this->user = $this->retrieveById($id);
            return $this->user;
        }

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
        $this->expireRememberCookie();
    }

    // ---- Remember-me helpers ------------------------------------------------

    /**
     * FIX C1: strict format validation before any processing.
     */
    protected function recallFromCookie(): mixed
    {
        $token = $_COOKIE[$this->cookieName] ?? null;

        if (!is_string($token) || !preg_match(self::REMEMBER_TOKEN_PATTERN, $token)) {
            return null;
        }

        if (!$this->provider instanceof DatabaseUserProvider) {
            return null;
        }

        [$userId, $rawToken] = explode('|', $token, 2);

        $userId = (int) $userId;
        if ($userId <= 0) {
            return null;
        }

        $user = $this->provider->verifyRememberToken($userId, $rawToken);
        if ($user !== null) {
            $this->guardSession();
            $_SESSION[$this->sessionKey] = $userId;
            $this->user         = $user;
            $this->userResolved = true;
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

    /**
     * FIX AUDIT-5: configure session cookie params before session_start().
     *
     * Sets HttpOnly, SameSite=Lax, and Secure (on HTTPS) on the PHP session
     * cookie. These params must be set before the session is opened;
     * calling session_set_cookie_params() on an already-active session is
     * a no-op in PHP, so the guard checks PHP_SESSION_NONE first.
     */
    protected function guardSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $secure   = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $lifetime = (int) ($this->config['session_lifetime'] ?? 0);

            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path'     => '/',
                'domain'   => $this->config['session_domain'] ?? '',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => $this->config['session_samesite'] ?? 'Lax',
            ]);

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
