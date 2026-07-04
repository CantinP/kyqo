<?php

namespace App\Providers;

use Kyqo\Core\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Define model -> policy mappings.
     *
     * Example:
     *   \App\Models\Post::class => \App\Policies\PostPolicy::class,
     */
    protected array $policies = [];

    public function register(): void {}

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            $this->app->make('auth.gate')->policy($model, $policy);
        }
    }
}
