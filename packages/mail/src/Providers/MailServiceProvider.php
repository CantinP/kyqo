<?php

namespace Kyqo\Mail\Providers;

use Kyqo\Core\Providers\ServiceProvider;
use Kyqo\Mail\MailManager;

class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MailManager::class, function () {
            return new MailManager(
                $this->app->make('config')->get('mail', [])
            );
        });
        $this->app->singleton('mail', fn () => $this->app->make(MailManager::class));
    }
}
