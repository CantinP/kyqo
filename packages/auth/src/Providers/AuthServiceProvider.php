<?php

namespace Kyqo\Auth\Providers;

use Kyqo\Core\Providers\ServiceProvider;
use Kyqo\Auth\AuthManager;
use Kyqo\Core\Hashing\Hasher;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Hasher::class, function () {
            $hashConfig = $this->app->make('config')->get('hashing', []);
            return new Hasher($hashConfig);
        });
        $this->app->singleton('hash', fn () => $this->app->make(Hasher::class));

        $this->app->singleton(AuthManager::class, function () {
            return new AuthManager(
                $this->app->make('config')->get('auth', [])
            );
        });
        $this->app->singleton('auth', fn () => $this->app->make(AuthManager::class));
    }
}
