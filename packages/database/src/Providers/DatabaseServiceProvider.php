<?php

namespace Kyqo\Database\Providers;

use Kyqo\Core\Providers\ServiceProvider;
use Kyqo\Database\DatabaseManager;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatabaseManager::class, function () {
            return new DatabaseManager(
                $this->app->make('config')->get('database', [])
            );
        });
        $this->app->singleton('db', fn () => $this->app->make(DatabaseManager::class));
    }
}
