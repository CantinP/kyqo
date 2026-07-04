<?php

namespace Kyqo\Core\Providers;

use Kyqo\Core\Events\Dispatcher;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Events to listeners mapping.
     * Override in your application's EventServiceProvider.
     *
     * @var array<string, array<string>>
     */
    protected array $listen = [];

    public function register(): void
    {
        $this->app->singleton(Dispatcher::class, fn () => new Dispatcher());
        $this->app->singleton('events', fn () => $this->app->make(Dispatcher::class));
    }

    public function boot(): void
    {
        $dispatcher = $this->app->make('events');
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                $dispatcher->listen($event, $listener);
            }
        }
    }
}
