<?php

namespace Kyqo\View\Providers;

use Kyqo\Core\Providers\ServiceProvider;
use Kyqo\View\Engine;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Engine::class, function () {
            $config   = $this->app->make('config');
            $paths    = (array)  ($config->get('view.paths')    ?? []);
            $compiled = (string) ($config->get('view.compiled') ?? sys_get_temp_dir() . '/kyqo_views');
            $cache    = (bool)   ($config->get('view.cache', true));
            return new Engine($paths, $compiled, $cache);
        });
        $this->app->singleton('view', fn () => $this->app->make(Engine::class));
    }
}
