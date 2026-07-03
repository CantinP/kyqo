<?php

namespace Kyqo\Auth;

use Kyqo\Core\Application;
use Kyqo\Database\DatabaseManager;

/**
 * Kyqo Auth Manager
 *
 * Manages authentication guards and providers.
 * Supports session-based and token-based authentication.
 * Resolves DatabaseUserProvider and ArrayUserProvider automatically from config.
 */
class AuthManager
{
    protected array $config;
    protected array $guards    = [];
    protected array $providers = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function guard(?string $name = null): GuardInterface
    {
        $name ??= $this->config['defaults']['guard'] ?? 'web';

        if (!isset($this->guards[$name])) {
            $this->guards[$name] = $this->resolve($name);
        }

        return $this->guards[$name];
    }

    public function user(): mixed           { return $this->guard()->user(); }
    public function check(): bool           { return $this->guard()->check(); }
    public function guest(): bool           { return !$this->check(); }
    public function id(): mixed             { return $this->guard()->id(); }
    public function attempt(array $credentials): bool  { return $this->guard()->attempt($credentials); }
    public function login(mixed $user): void            { $this->guard()->login($user); }
    public function logout(): void                      { $this->guard()->logout(); }

    // ---- Resolution ---------------------------------------------------------

    protected function resolve(string $name): GuardInterface
    {
        $config = $this->config['guards'][$name]
            ?? throw new \InvalidArgumentException("Auth guard [{$name}] is not defined.");

        $providerName = $config['provider'] ?? null;
        $provider     = $providerName ? $this->resolveProvider($providerName) : null;

        return match ($config['driver']) {
            'session' => new Guards\SessionGuard($name, $config, $provider),
            'token'   => new Guards\TokenGuard($name, $config, $provider),
            default   => throw new \InvalidArgumentException(
                "Auth guard driver [{$config['driver']}] is not supported."
            ),
        };
    }

    protected function resolveProvider(string $name): ?UserProviderInterface
    {
        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }

        $config = $this->config['providers'][$name] ?? null;
        if ($config === null) {
            return null;
        }

        $driver = $config['driver'] ?? 'database';

        $this->providers[$name] = match ($driver) {
            'eloquent', 'database' => $this->makeDatabaseProvider($config),
            'array'                => $this->makeArrayProvider($config),
            default                => throw new \InvalidArgumentException(
                "Auth provider driver [{$driver}] is not supported."
            ),
        };

        return $this->providers[$name];
    }

    protected function makeDatabaseProvider(array $config): Providers\DatabaseUserProvider
    {
        try {
            $db = Application::getInstance()->make(DatabaseManager::class);
        } catch (\Throwable) {
            $db = new DatabaseManager([]);
        }
        return new Providers\DatabaseUserProvider($db, $config);
    }

    protected function makeArrayProvider(array $config): Providers\ArrayUserProvider
    {
        return new Providers\ArrayUserProvider($config['users'] ?? []);
    }
}
