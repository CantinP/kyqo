<?php

namespace Kyqo\Core;

use Kyqo\Core\Container\Container;
use Kyqo\Core\Config\Repository as ConfigRepository;
use Kyqo\Core\Events\Dispatcher;
use Kyqo\Auth\AuthManager;
use Kyqo\View\Engine as ViewEngine;

/**
 * The Kyqo Application.
 *
 * FIX MINOR-FINAL-2 + SEC-FINAL-3:
 * - ConfigRepository is now bootstrapped as a loaded singleton (reads config/*.php)
 * - AuthManager is bound with the real auth config from config/auth.php
 * - ViewEngine, Dispatcher bound as singletons
 * - Core aliases properly resolve to loaded instances
 */
class Application extends Container
{
    public const VERSION = '0.1.0';

    protected string $basePath;
    protected bool $bootstrapped = false;
    protected array $serviceProviders = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '\/');

        $this->registerBaseBindings();
        $this->registerCoreServices();
        $this->registerCoreAliases();
    }

    public function version(): string { return static::VERSION; }

    // ---- Path helpers -------------------------------------------------------

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }

    public function appPath(string $path = ''): string      { return $this->basePath('app' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '')); }
    public function configPath(string $path = ''): string   { return $this->basePath('config' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '')); }
    public function databasePath(string $path = ''): string { return $this->basePath('database' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '')); }
    public function resourcePath(string $path = ''): string { return $this->basePath('resources' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '')); }
    public function storagePath(string $path = ''): string  { return $this->basePath('storage' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '')); }
    public function publicPath(string $path = ''): string   { return $this->basePath('public' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '')); }

    // ---- Bootstrap ----------------------------------------------------------

    public function bootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }
        $this->bootstrapped = true;
    }

    public function isDebug(): bool
    {
        try {
            return (bool) $this->make('config')->get('app.debug', false);
        } catch (\Throwable) {
            return false;
        }
    }

    public function environment(): string
    {
        try {
            return (string) $this->make('config')->get('app.env', 'production');
        } catch (\Throwable) {
            return 'production';
        }
    }

    // ---- Core bindings ------------------------------------------------------

    protected function registerBaseBindings(): void
    {
        static::setInstance($this);
        $this->instance('app',               $this);
        $this->instance(Container::class,    $this);
        $this->instance(Application::class,  $this);
    }

    /**
     * FIX MINOR-FINAL-2 + SEC-FINAL-3:
     * Register all core services as pre-loaded singletons so that:
     *   - config('app.debug') actually reads from config/*.php files
     *   - AuthManager receives the real auth config (not an empty array)
     *   - ViewEngine, Dispatcher are available via container
     */
    protected function registerCoreServices(): void
    {
        // 1. Config — load all files from /config immediately
        $config = new ConfigRepository();
        $configPath = $this->configPath();
        if (is_dir($configPath)) {
            $config->loadFromPath($configPath);
        }
        $this->instance(ConfigRepository::class, $config);
        $this->instance('config', $config);

        // 2. Events dispatcher
        $dispatcher = new Dispatcher();
        $this->instance(Dispatcher::class, $dispatcher);
        $this->instance('events', $dispatcher);

        // 3. AuthManager — injected with real config/auth.php
        //    SEC-FINAL-3: previously bound with [] causing crash on guard resolution
        $authConfig = $config->get('auth', []);
        $auth = new AuthManager($authConfig);
        $this->instance(AuthManager::class, $auth);
        $this->instance('auth', $auth);

        // 4. View Engine — bind lazily (needs config for paths)
        $this->singleton(ViewEngine::class, function () use ($config) {
            $paths       = (array) ($config->get('view.paths') ?? []);
            $compiled    = (string) ($config->get('view.compiled') ?? sys_get_temp_dir() . '/kyqo_views');
            $cache       = (bool)   ($config->get('view.cache', true));
            return new ViewEngine($paths, $compiled, $cache);
        });
        $this->singleton('view', fn () => $this->make(ViewEngine::class));
    }

    /**
     * Core alias registrations.
     * All aliases point to already-bound instances registered above.
     */
    protected function registerCoreAliases(): void
    {
        // Aliases are already satisfied by instance() calls above.
        // These provide FQCN -> short-key resolution for make().
        // (alias() registers short-key -> FQCN direction in Container)
        $aliases = [
            ConfigRepository::class => 'config',
            Dispatcher::class       => 'events',
            AuthManager::class      => 'auth',
            ViewEngine::class       => 'view',
        ];

        foreach ($aliases as $abstract => $shortKey) {
            // Guard: don't register duplicate aliases
            if (!$this->bound($shortKey)) {
                $this->alias($abstract, $shortKey);
            }
        }
    }
}
