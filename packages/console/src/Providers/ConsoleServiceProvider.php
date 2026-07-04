<?php

namespace Kyqo\Console\Providers;

use Kyqo\Core\Providers\ServiceProvider;
use Kyqo\Console\Kernel;

class ConsoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Kernel::class, fn () => new Kernel($this->app));
        $this->app->singleton('console', fn () => $this->app->make(Kernel::class));
    }
}
