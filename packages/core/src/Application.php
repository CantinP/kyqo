<?php

namespace Kyqo\Core;

use Kyqo\Core\Container\Container;
use Kyqo\Core\Config\Repository as ConfigRepository;
use Kyqo\Core\Events\Dispatcher;

/**
 * The Kyqo Application.
 *
 * FIX A1: Added setBasePath() so bootstrap/app.php can call
 *         Application::getInstance()->setBasePath(...) after the fact.
 *         __construct() now accepts an optional $basePath.
 */
class Application extends Container
{
    public const VERSION = '0.1.0';

    protected string $basePath         = '';
    protected bool   $bootstrapped     = false;
    protected array  $serviceProviders = [];

    public function __construct(string $basePath = '')
    {
        if ($basePath !== '') {
            $this->setBasePath($basePath);
        }

        $this->registerBaseBindings();
        $this->registerCoreServices();
        $this->registerCoreAliases();
    }

    /** FIX A1 – allow late binding of the base path (called by bootstrap/app.php). */
    public function setBasePath(string $basePath): static
    {
        $this->basePath = rtrim($basePath, '\/');
        return $this;
    }

    public function version(): string { return static::VERSION; }

    // ── Path helpers ───────────────────────────────────────────────────────

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

    // ── Bootstrap ─────────────────────────────────────────────────────────

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

    // ── Provider registration ─────────────────────────────────────────────

    public function register(object $provider): void
    {
        $provider->register();
        $this->serviceProviders[] = $provider;
        if (method_exists($provider, 'boot')) {
            $provider->boot();
        }
    }

    // ── Core bindings ─────────────────────────────────────────────────────

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
        $this->instance(ConfigRepository::class, $config);
        $this->instance('config', $config);

        // Lazy-load config from disk only once basePath is set
        $this->singleton('config.loaded', function () use ($config) {
            $configPath = $this->configPath();
            if ($this->basePath !== '' && is_dir($configPath)) {
                $config->loadFromPath($configPath);
            }
            return true;
        });

        // 2. Events
        $dispatcher = new Dispatcher();
        $this->instance(Dispatcher::class, $dispatcher);
        $this->instance('events', $dispatcher);

        // 3. Router
        $this->singleton('router', fn () => new \Kyqo\Http\Router\Router());
        $this->singleton(\Kyqo\Http\Router\Router::class, fn () => $this->make('router'));

        // 4. Database
        $this->singleton('db', function () {
            $this->make('config.loaded');
            return new \Kyqo\Database\DatabaseManager($this->make('config')->get('database', []));
        });
        $this->singleton(\Kyqo\Database\DatabaseManager::class, fn () => $this->make('db'));
        $this->singleton(\Kyqo\Database\Schema\Migrator::class, function () {
            return new \Kyqo\Database\Schema\Migrator(
                $this->make('db'),
                $this->databasePath('migrations')
            );
        });

        // 5. Auth
        $this->singleton('auth', function () {
            $this->make('config.loaded');
            return new \Kyqo\Auth\AuthManager($this->make('config')->get('auth', []));
        });
        $this->singleton(\Kyqo\Auth\AuthManager::class, fn () => $this->make('auth'));

        // 6. Cache
        $this->singleton('cache', function () {
            $this->make('config.loaded');
            return new \Kyqo\Cache\CacheManager($this->make('config')->get('cache', []));
        });
        $this->singleton(\Kyqo\Cache\CacheManager::class, fn () => $this->make('cache'));

        // 7. Queue
        $this->singleton('queue', function () {
            $this->make('config.loaded');
            return new \Kyqo\Queue\QueueManager($this->make('config')->get('queue', []));
        });
        $this->singleton(\Kyqo\Queue\QueueManager::class, fn () => $this->make('queue'));

        // 8. View Engine
        $this->singleton('view', function () {
            $this->make('config.loaded');
            $cfg      = $this->make('config');
            $paths    = (array)  ($cfg->get('view.paths')    ?? [$this->resourcePath('views')]);
            $compiled = (string) ($cfg->get('view.compiled') ?? $this->storagePath('framework/views'));
            $cache    = (bool)   ($cfg->get('view.cache', true));
            return new \Kyqo\View\Engine($paths, $compiled, $cache);
        });
        $this->singleton(\Kyqo\View\Engine::class, fn () => $this->make('view'));

        // 9. Request
        $this->singleton('request', fn () => \Kyqo\Http\Request::capture());
        $this->singleton(\Kyqo\Http\Request::class, fn () => $this->make('request'));

        // 10. Session
        $this->singleton('session', fn () => new \Kyqo\Http\Session\Store());
        $this->singleton(\Kyqo\Http\Session\Store::class, fn () => $this->make('session'));

        // 11. URL Generator
        $this->singleton('url', fn () => new \Kyqo\Http\UrlGenerator(
            $this->make(\Kyqo\Http\Router\Router::class),
            $this->make('request')
        ));
        $this->singleton(\Kyqo\Http\UrlGenerator::class, fn () => $this->make('url'));

        // 12. Logger
        $this->singleton('log', function () {
            $this->make('config.loaded');
            $cfg     = $this->make('config');
            $channel = $cfg->get('logging.default', 'single');
            $path    = $cfg->get("logging.channels.{$channel}.path", $this->storagePath('logs/kyqo.log'));
            $level   = $cfg->get("logging.channels.{$channel}.level", 'debug');
            return new \Kyqo\Core\Logging\Logger($path, $level);
        });
        $this->singleton(\Kyqo\Core\Logging\Logger::class, fn () => $this->make('log'));

        // 13. Hasher
        $this->singleton('hash', function () {
            $this->make('config.loaded');
            return new \Kyqo\Core\Hashing\Hasher($this->make('config')->get('hashing', []));
        });
        $this->singleton(\Kyqo\Core\Hashing\Hasher::class, fn () => $this->make('hash'));

        // 14. Storage
        $this->singleton('storage', function () {
            $this->make('config.loaded');
            $cfg = $this->make('config')->get('filesystems', [
                'default' => 'local',
                'disks'   => [
                    'local'  => ['driver' => 'local', 'root' => $this->storagePath('app')],
                    'public' => ['driver' => 'local', 'root' => $this->storagePath('app/public')],
                ],
            ]);
            return new \Kyqo\Core\Storage\StorageManager($cfg);
        });
        $this->singleton(\Kyqo\Core\Storage\StorageManager::class, fn () => $this->make('storage'));

        // 15. Validator
        $this->singleton('validator', function () {
            try { $db = $this->make(\Kyqo\Database\DatabaseManager::class); } catch (\Throwable) { $db = null; }
            return new \Kyqo\Http\Validation\ValidatorFactory($db);
        });
        $this->singleton(\Kyqo\Http\Validation\ValidatorFactory::class, fn () => $this->make('validator'));

        // 16. Mail
        $this->singleton('mail', function () {
            $this->make('config.loaded');
            return new \Kyqo\Mail\MailManager($this->make('config')->get('mail', []));
        });
        $this->singleton(\Kyqo\Mail\MailManager::class, fn () => $this->make('mail'));

        // 17. Console Kernel
        $this->singleton(\Kyqo\Console\Kernel::class, fn () => new \Kyqo\Console\Kernel($this));
        $this->singleton('console', fn () => $this->make(\Kyqo\Console\Kernel::class));

        // 18. FIX A3 — bind App\Http\Kernel so make(\Kyqo\Http\Kernel::class) resolves
        //     to the application subclass.  The framework Kernel is bound as the app Kernel.
        $this->singleton(\Kyqo\Http\Kernel::class, function () {
            $this->make('config.loaded');
            $debug = (bool) $this->make('config')->get('app.debug', false);
            return new \App\Http\Kernel($this->make(\Kyqo\Http\Router\Router::class), ['debug' => $debug]);
        });
    }

    protected function registerCoreAliases(): void
    {
        $aliases = [
            ConfigRepository::class                              => 'config',
            Dispatcher::class                                    => 'events',
            \Kyqo\Http\Router\Router::class                     => 'router',
            \Kyqo\Database\DatabaseManager::class               => 'db',
            \Kyqo\Auth\AuthManager::class                       => 'auth',
            \Kyqo\Cache\CacheManager::class                     => 'cache',
            \Kyqo\Queue\QueueManager::class                     => 'queue',
            \Kyqo\View\Engine::class                            => 'view',
            \Kyqo\Http\Request::class                           => 'request',
            \Kyqo\Http\Session\Store::class                     => 'session',
            \Kyqo\Http\UrlGenerator::class                      => 'url',
            \Kyqo\Core\Logging\Logger::class                    => 'log',
            \Kyqo\Core\Hashing\Hasher::class                    => 'hash',
            \Kyqo\Core\Storage\StorageManager::class            => 'storage',
            \Kyqo\Http\Validation\ValidatorFactory::class       => 'validator',
            \Kyqo\Mail\MailManager::class                       => 'mail',
            \Kyqo\Console\Kernel::class                         => 'console',
        ];

        foreach ($aliases as $abstract => $shortKey) {
            if (!$this->isAlias($shortKey)) {
                $this->alias($abstract, $shortKey);
            }
        }
    }
}
