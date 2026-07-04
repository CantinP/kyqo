<?php

namespace Kyqo\Core\Providers;

use Kyqo\Core\Logging\Logger;

class LogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('log', function () {
            $config  = $this->app->make('config')->get('logging', []);
            $channel = $config['default'] ?? 'single';
            $path    = $config['channels'][$channel]['path']
                ?? $this->app->storagePath('logs/kyqo.log');
            $level   = $config['channels'][$channel]['level'] ?? 'debug';
            return new Logger($path, $level);
        });

        $this->app->singleton(Logger::class, fn () => $this->app->make('log'));
    }
}
