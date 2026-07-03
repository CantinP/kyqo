<?php

namespace Kyqo\Core;

use Kyqo\Core\Container\Container;
use Kyqo\Core\Config\Repository as ConfigRepository;
use Kyqo\Core\Events\Dispatcher;

/**
 * The Kyqo Application.
 *
 * BUG FIX: registerCoreAliases() now uses the correct alias() direction:
 *   alias(abstract: FQCN, alias: short_key)
 *   so $app->make(ConfigRepository::class) resolves correctly.
 */
class Application extends Container
{
    public const VERSION = '0.1.0';

    protected string $basePath;
    protected bool $bootstrapped = false;
    protected array $serviceProviders = [];
    protected array $deferredServices  = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '\/');

        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
        $this->registerCoreAliases();
    }

    public function version(): string  { return static::VERSION; }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    public function appPath(string $path = ''): string
    {
        return $this->basePath('app') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath('config') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    public function databasePath(string $path = ''): string
    {
        return $this->basePath('database') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    public function resourcePath(string $path = ''): string
    {
        return $this->basePath('resources') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    public function publicPath(string $path = ''): string
    {
        return $this->basePath('public') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

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
            return $this->make('config')->get('app.env', 'production');
        } catch (\Throwable) {
            return 'production';
        }
    }

    protected function registerBaseBindings(): void
    {
        static::setInstance($this);

        $this->instance('app', $this);
        $this->instance(Container::class, $this);
        $this->instance(Application::class, $this);
    }

    protected function registerBaseServiceProviders(): void
    {
        // Loaded lazily via config/app.php providers array
    }

    /**
     * Register core class aliases.
     *
     * BUG FIX: alias() signature is alias(string $abstract, string $alias).
     * The short string key ('config', 'events', 'log') is the ALIAS.
     * The FQCN is the ABSTRACT that gets resolved.
     *
     * Correct call: $this->alias(FQCN, short_key)
     * i.e. $app->make(ConfigRepository::class) === $app->make('config')
     */
    protected function registerCoreAliases(): void
    {
        $aliases = [
            ConfigRepository::class => 'config',
            Dispatcher::class       => 'events',
        ];

        foreach ($aliases as $abstract => $shortKey) {
            $this->alias($abstract, $shortKey);
        }
    }
}
