<?php

namespace App\Providers;

use Kyqo\Core\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind your own services here.
        // Example: $this->app->singleton(MyService::class, fn () => new MyService());
    }

    public function boot(): void
    {
        // Bootstrap application services.
    }
}
