<?php

namespace Kyqo\Queue\Providers;

use Kyqo\Core\Providers\ServiceProvider;
use Kyqo\Queue\QueueManager;

class QueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QueueManager::class, function () {
            return new QueueManager(
                $this->app->make('config')->get('queue', [])
            );
        });
        $this->app->singleton('queue', fn () => $this->app->make(QueueManager::class));
    }
}
