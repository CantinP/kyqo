<?php

namespace Kyqo\Core\Providers;

use Kyqo\Core\Application;

/**
 * Base Service Provider
 */
abstract class ServiceProvider
{
    public function __construct(protected Application $app) {}

    /**
     * Register bindings into the container.
     */
    abstract public function register(): void;

    /**
     * Bootstrap any application services (called after all providers are registered).
     */
    public function boot(): void {}
}
