<?php

namespace Kyqo\Core;

use Kyqo\Core\Container\Container;
use Kyqo\Core\Config\Repository as ConfigRepository;
use Kyqo\Core\Events\Dispatcher;

/**
 * The Kyqo Application.
 *
 * MINOR-V4-1 FIX: ViewEngine, CacheManager, QueueManager are bound lazily
 * via closures — NO top-level `use` import of these classes.
 * If a package is absent, the app boots fine; the error surfaces only
 * when the specific service is first resolved.
 *
 * BUG-V4-1 FIX: CacheManager and QueueManager are now registered with their
 * real configs (cache.php and queue.php) as singletons.
 */
class Application extends Container
{
    public const VERSION = '0.1.0';

    protected string $basePath;
    protected bool   $bootstrapped    = false;
    protected array  $serviceProviders = [];

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

    public function appPath(string $path = ''): string
    {
        return $this->basePath('app' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath('config' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
    }

    public function databasePath(string $path = ''): string
    {
        return $this->basePath('database' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
    }

    public function resourcePath(string $path = ''): string
    {
        return $this->basePath('resources' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
    }

    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
    }

    public function publicPath(string $path = ''): string
    {
        return $this->basePath('public' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
    }

    // ---- Bootstrap ----------------------------------------------------------

    public function bootstrap(): void
    {
        if ($this->bootstrapped) return;
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
        $this->instance('app',              $this);
        $this->instance(Container::class,   $this);
        $this->instance(Application::class, $this);
    }

    protected function registerCoreServices(): void
    {
        // 1. Config — load all config/*.php files immediately
        $config = new ConfigRepository();
        $configPath = $this->configPath();
        if (is_dir($configPath)) {
            $config->loadFromPath($configPath);
        }
        $this->instance(ConfigRepository::class, $config);
        $this->instance('config', $config);

        // 2. Events
        $dispatcher = new Dispatcher();
        $this->instance(Dispatcher::class, $dispatcher);
        $this->instance('events', $dispatcher);

        // 3. Auth — real config from config/auth.php
        $authConfig = $config->get('auth', []);
        $this->singleton('auth', function () use ($authConfig) {
            return new \Kyqo\Auth\AuthManager($authConfig);
        });
        $this->singleton(\Kyqo\Auth\AuthManager::class, fn () => $this->make('auth'));

        // 4. Cache — real config from config/cache.php
        //    BUG-V4-1 FIX: bound as singleton with real config
        $cacheConfig = $config->get('cache', []);
        $this->singleton('cache', function () use ($cacheConfig) {
            return new \Kyqo\Cache\CacheManager($cacheConfig);
        });
        $this->singleton(\Kyqo\Cache\CacheManager::class, fn () => $this->make('cache'));

        // 5. Queue — real config from config/queue.php
        //    BUG-V4-1 FIX: bound as singleton with real config
        $queueConfig = $config->get('queue', []);
        $this->singleton('queue', function () use ($queueConfig) {
            return new \Kyqo\Queue\QueueManager($queueConfig);
        });
        $this->singleton(\Kyqo\Queue\QueueManager::class, fn () => $this->make('queue'));

        // 6. View Engine — lazy, no top-level import (MINOR-V4-1 FIX)
        $this->singleton('view', function () use ($config) {
            $paths    = (array)  ($config->get('view.paths')    ?? []);
            $compiled = (string) ($config->get('view.compiled') ?? sys_get_temp_dir() . '/kyqo_views');
            $cache    = (bool)   ($config->get('view.cache', true));
            return new \Kyqo\View\Engine($paths, $compiled, $cache);
        });
        $this->singleton(\Kyqo\View\Engine::class, fn () => $this->make('view'));
    }

    /**
     * Register short-key aliases.
     * All singletons already bound above — aliases simply add FQCN -> short-key direction.
     */
    protected function registerCoreAliases(): void
    {
        $aliases = [
            ConfigRepository::class             => 'config',
            Dispatcher::class                   => 'events',
            \Kyqo\Auth\AuthManager::class       => 'auth',
            \Kyqo\Cache\CacheManager::class     => 'cache',
            \Kyqo\Queue\QueueManager::class     => 'queue',
            \Kyqo\View\Engine::class            => 'view',
        ];

        foreach ($aliases as $abstract => $shortKey) {
            if (!$this->isAlias($shortKey)) {
                $this->alias($abstract, $shortKey);
            }
        }
    }
}
