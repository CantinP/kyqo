<?php

namespace Kyqo\Core;

use Kyqo\Core\Container\Container;
use Kyqo\Core\Config\Repository as ConfigRepository;
use Kyqo\Core\Events\Dispatcher;
use Kyqo\Core\Exceptions\Handler;
use Kyqo\Core\Logger\LogManager;

/**
 * The Kyqo Application.
 *
 * The heart of the framework — a service container that
 * binds, resolves and bootstraps all framework components.
 */
class Application extends Container
{
    /**
     * The Kyqo framework version.
     */
    public const VERSION = '0.1.0';

    /**
     * The base path of the application.
     */
    protected string $basePath;

    /**
     * Indicates if the application has been bootstrapped.
     */
    protected bool $bootstrapped = false;

    /**
     * All registered service providers.
     */
    protected array $serviceProviders = [];

    /**
     * Deferred services to be resolved lazily.
     */
    protected array $deferredServices = [];

    /**
     * Create a new Kyqo application instance.
     */
    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '\/');

        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
        $this->registerCoreAliases();
    }

    /**
     * Get the version number of the application.
     */
    public function version(): string
    {
        return static::VERSION;
    }

    /**
     * Get the base path of the application.
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath.($path ? DIRECTORY_SEPARATOR.$path : '');
    }

    /**
     * Get the app path.
     */
    public function appPath(string $path = ''): string
    {
        return $this->basePath('app').($path ? DIRECTORY_SEPARATOR.$path : '');
    }

    /**
     * Get the config path.
     */
    public function configPath(string $path = ''): string
    {
        return $this->basePath('config').($path ? DIRECTORY_SEPARATOR.$path : '');
    }

    /**
     * Get the database path.
     */
    public function databasePath(string $path = ''): string
    {
        return $this->basePath('database').($path ? DIRECTORY_SEPARATOR.$path : '');
    }

    /**
     * Get the resources path.
     */
    public function resourcePath(string $path = ''): string
    {
        return $this->basePath('resources').($path ? DIRECTORY_SEPARATOR.$path : '');
    }

    /**
     * Get the storage path.
     */
    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage').($path ? DIRECTORY_SEPARATOR.$path : '');
    }

    /**
     * Get the public path.
     */
    public function publicPath(string $path = ''): string
    {
        return $this->basePath('public').($path ? DIRECTORY_SEPARATOR.$path : '');
    }

    /**
     * Bootstrap the application.
     */
    public function bootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }

        $this->bootstrapped = true;
    }

    /**
     * Determine if the application is in debug mode.
     */
    public function isDebug(): bool
    {
        return (bool) $this->make('config')->get('app.debug', false);
    }

    /**
     * Get the current application environment.
     */
    public function environment(): string
    {
        return $this->make('config')->get('app.env', 'production');
    }

    /**
     * Register the basic bindings into the container.
     */
    protected function registerBaseBindings(): void
    {
        static::setInstance($this);

        $this->instance('app', $this);
        $this->instance(Container::class, $this);
        $this->instance(Application::class, $this);
    }

    /**
     * Register all base service providers.
     */
    protected function registerBaseServiceProviders(): void
    {
        // Registered lazily via config/app.php providers
    }

    /**
     * Register the core class aliases.
     */
    protected function registerCoreAliases(): void
    {
        foreach ([
            'app'    => [self::class, Container::class],
            'config' => [ConfigRepository::class],
            'events' => [Dispatcher::class],
            'log'    => [LogManager::class],
        ] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }
}
