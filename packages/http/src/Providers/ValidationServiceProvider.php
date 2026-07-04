<?php

namespace Kyqo\Http\Providers;

use Kyqo\Core\Providers\ServiceProvider;
use Kyqo\Http\Validation\ValidatorFactory;
use Kyqo\Database\DatabaseManager;

class ValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ValidatorFactory::class, function () {
            try {
                $db = $this->app->make(DatabaseManager::class);
            } catch (\Throwable) {
                $db = null;
            }
            return new ValidatorFactory($db);
        });
        $this->app->singleton('validator', fn () => $this->app->make(ValidatorFactory::class));
    }
}
