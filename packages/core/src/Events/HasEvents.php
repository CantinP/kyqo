<?php

namespace Kyqo\Core\Events;

/**
 * HasEvents trait — mix into any class (typically Model) to fire events.
 *
 * Usage:
 *   class User extends Model
 *   {
 *       use HasEvents;
 *
 *       protected static array $observables = ['created', 'updated', 'deleted'];
 *   }
 *
 *   User::creating(fn($user) => Log::info('Creating user'));
 *   User::observe(UserObserver::class);
 */
trait HasEvents
{
    protected static array $modelObservers = [];
    protected static array $modelListeners = [];

    /**
     * Register an observer class.
     * The observer should have methods named after ORM events: creating, created, etc.
     */
    public static function observe(object|string $observer): void
    {
        $instance = is_string($observer) ? new $observer() : $observer;
        $events   = static::getObservableEvents();

        foreach ($events as $event) {
            if (method_exists($instance, $event)) {
                static::on($event, \Closure::fromCallable([$instance, $event]));
            }
        }

        static::$modelObservers[static::class][] = $instance;
    }

    /**
     * Register a Closure listener for a model event.
     */
    public static function on(string $event, \Closure $callback): void
    {
        static::$modelListeners[static::class][$event][] = $callback;
    }

    // Convenience aliases matching Eloquent’s API
    public static function creating(\Closure $cb): void   { static::on('creating',   $cb); }
    public static function created(\Closure $cb): void    { static::on('created',    $cb); }
    public static function updating(\Closure $cb): void   { static::on('updating',   $cb); }
    public static function updated(\Closure $cb): void    { static::on('updated',    $cb); }
    public static function saving(\Closure $cb): void     { static::on('saving',     $cb); }
    public static function saved(\Closure $cb): void      { static::on('saved',      $cb); }
    public static function deleting(\Closure $cb): void   { static::on('deleting',   $cb); }
    public static function deleted(\Closure $cb): void    { static::on('deleted',    $cb); }

    /**
     * Fire a model event and return false if any listener returns false
     * (which cancels the operation for "before" events like creating/updating).
     */
    protected function fireModelEvent(string $event): bool
    {
        $listeners = static::$modelListeners[static::class][$event] ?? [];
        foreach ($listeners as $listener) {
            if ($listener($this) === false) return false;
        }
        return true;
    }

    public static function getObservableEvents(): array
    {
        return ['creating', 'created', 'updating', 'updated', 'saving', 'saved', 'deleting', 'deleted'];
    }
}
