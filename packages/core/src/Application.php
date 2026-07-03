<?php

namespace Kyqo\Core;

use Kyqo\Core\Container\Container;
use Kyqo\Core\Config\Repository as ConfigRepository;
use Kyqo\Core\Events\Dispatcher;

/**
 * The Kyqo Application.
 *
 * FIX M1: Router is now registered as a singleton so that UrlGenerator
 * and Kernel can always resolve it from the container. If a custom Router
 * is already bound before bootstrap, the existing binding is preserved.
 */
class Application extends Container
{
    public const VERSION = '0.1.0';

    protected string $basePath;
    protected bool   $bootstrapped     = false;
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
        // 1. Config
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

        // 3. Router — FIX M1: bind before UrlGenerator so make(Router::class) never fails
        $this->singleton('router', function () {
            return new \Kyqo\Http\Router\Router();
        });
        $this->singleton(\Kyqo\Http\Router\Router::class, fn () => $this->make('router'));

        // 4. Database
        $dbConfig = $config->get('database', []);
        $this->singleton('db', function () use ($dbConfig) {
            return new \Kyqo\Database\DatabaseManager($dbConfig);
        });
        $this->singleton(\Kyqo\Database\DatabaseManager::class, fn () => $this->make('db'));

        // 5. Auth
        $authConfig = $config->get('auth', []);
        $this->singleton('auth', function () use ($authConfig) {
            return new \Kyqo\Auth\AuthManager($authConfig);
        });
        $this->singleton(\Kyqo\Auth\AuthManager::class, fn () => $this->make('auth'));

        // 6. Cache
        $cacheConfig = $config->get('cache', []);
        $this->singleton('cache', function () use ($cacheConfig) {
            return new \Kyqo\Cache\CacheManager($cacheConfig);
        });
        $this->singleton(\Kyqo\Cache\CacheManager::class, fn () => $this->make('cache'));

        // 7. Queue
        $queueConfig = $config->get('queue', []);
        $this->singleton('queue', function () use ($queueConfig) {
            return new \Kyqo\Queue\QueueManager($queueConfig);
        });
        $this->singleton(\Kyqo\Queue\QueueManager::class, fn () => $this->make('queue'));

        // 8. View Engine
        $this->singleton('view', function () use ($config) {
            $paths    = (array)  ($config->get('view.paths')    ?? []);
            $compiled = (string) ($config->get('view.compiled') ?? sys_get_temp_dir() . '/kyqo_views');
            $cache    = (bool)   ($config->get('view.cache', true));
            return new \Kyqo\View\Engine($paths, $compiled, $cache);
        });
        $this->singleton(\Kyqo\View\Engine::class, fn () => $this->make('view'));

        // 9. Request
        $this->singleton('request', function () {
            return \Kyqo\Http\Request::capture();
        });
        $this->singleton(\Kyqo\Http\Request::class, fn () => $this->make('request'));

        // 10. Session store
        $this->singleton('session', function () {
            return new \Kyqo\Http\Session\Store();
        });
        $this->singleton(\Kyqo\Http\Session\Store::class, fn () => $this->make('session'));

        // 11. URL Generator
        $this->singleton('url', function () {
            return new \Kyqo\Http\UrlGenerator(
                $this->make(\Kyqo\Http\Router\Router::class),
                $this->make('request')
            );
        });
        $this->singleton(\Kyqo\Http\UrlGenerator::class, fn () => $this->make('url'));
    }

    protected function registerCoreAliases(): void
    {
        $aliases = [
            ConfigRepository::class                    => 'config',
            Dispatcher::class                          => 'events',
            \Kyqo\Http\Router\Router::class            => 'router',
            \Kyqo\Database\DatabaseManager::class     => 'db',
            \Kyqo\Auth\AuthManager::class              => 'auth',
            \Kyqo\Cache\CacheManager::class            => 'cache',
            \Kyqo\Queue\QueueManager::class            => 'queue',
            \Kyqo\View\Engine::class                   => 'view',
            \Kyqo\Http\Request::class                  => 'request',
            \Kyqo\Http\Session\Store::class            => 'session',
            \Kyqo\Http\UrlGenerator::class             => 'url',
        ];

        foreach ($aliases as $abstract => $shortKey) {
            if (!$this->isAlias($shortKey)) {
                $this->alias($abstract, $shortKey);
            }
        }
    }
}
