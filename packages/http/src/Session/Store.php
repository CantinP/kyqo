<?php

namespace Kyqo\Http\Session;

/**
 * Session store — thin wrapper around PHP native sessions.
 *
 * FIX #7: constructor does NOT call session_start() when a session is
 * already active (PHP_SESSION_ACTIVE). bootstrap/app.php is responsible
 * for starting the session with secure cookie parameters; this class
 * must not override that.
 */
class Store
{
    protected string $flashPrefix = '_flash_';

    public function __construct()
    {
        // FIX #7: only start if not already running.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // PHP_SESSION_DISABLED → let it surface naturally (server mis-config).
    }

    // ---- Basic read/write ---------------------------------------------------

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function put(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    public function all(): array
    {
        return $_SESSION;
    }

    public function flush(): void
    {
        $_SESSION = [];
    }

    // ---- Flash data ---------------------------------------------------------

    public function flash(string $key, mixed $value): void
    {
        $_SESSION[$this->flashPrefix . $key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $flashKey = $this->flashPrefix . $key;
        $value    = $_SESSION[$flashKey] ?? $default;
        unset($_SESSION[$flashKey]);
        return $value;
    }

    public function hasFlash(string $key): bool
    {
        return isset($_SESSION[$this->flashPrefix . $key]);
    }

    // ---- Increment / decrement ----------------------------------------------

    public function increment(string $key, int $by = 1): int
    {
        $value = (int) $this->get($key, 0) + $by;
        $this->set($key, $value);
        return $value;
    }

    public function decrement(string $key, int $by = 1): int
    {
        return $this->increment($key, -$by);
    }

    // ---- Regenerate ---------------------------------------------------------

    public function regenerate(bool $deleteOld = true): bool
    {
        return session_regenerate_id($deleteOld);
    }

    public function getId(): string
    {
        return session_id();
    }
}
