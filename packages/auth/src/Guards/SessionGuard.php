<?php

namespace Kyqo\Auth\Guards;

use Kyqo\Auth\GuardInterface;
use Kyqo\Auth\UserProviderInterface;
use Kyqo\Auth\Providers\DatabaseUserProvider;

/**
 * Session-based authentication guard.
 *
 * FIX C1: recallFromCookie() now validates the cookie format with a strict
 * regex before any processing. Malformed, empty, or injected cookie values
 * are rejected immediately — preventing a userId=0 match via (int) cast
 * on a cookie that contains no pipe separator.
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
     *
     * Cookie must match "<digits>|<80 hex chars>".
     * bin2hex(random_bytes(40)) produces exactly 80 hex characters.
     * Any cookie that does not match is silently discarded — no DB hit,
     * no (int) cast on garbage input.
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

        // userId was validated as \d+ so cast is safe; 0 is still rejected
        // by verifyRememberToken() if no user with id=0 exists.
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
