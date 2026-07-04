<?php

namespace Kyqo\Cache\Providers;

use Kyqo\Core\Providers\ServiceProvider;
use Kyqo\Cache\CacheManager;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CacheManager::class, function () {
            return new CacheManager(
                $this->app->make('config')->get('cache', [])
            );
        });
        $this->app->singleton('cache', fn () => $this->app->make(CacheManager::class));
    }
}
