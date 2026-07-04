<?php

namespace Kyqo\Core\Providers;

use Kyqo\Http\Router\Router;
use Kyqo\Http\UrlGenerator;
use Kyqo\Http\Kernel;

class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Router::class, fn () => new Router());
        $this->app->singleton('router',      fn () => $this->app->make(Router::class));

        $this->app->singleton(UrlGenerator::class, function () {
            return new UrlGenerator(
                $this->app->make(Router::class),
                $this->app->make('request')
            );
        });
        $this->app->singleton('url', fn () => $this->app->make(UrlGenerator::class));

        $this->app->singleton(Kernel::class, function () {
            return new Kernel(
                $this->app->make(Router::class),
                ['debug' => $this->app->isDebug()]
            );
        });
    }
}
