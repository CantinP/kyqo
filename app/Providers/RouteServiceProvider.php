<?php

namespace App\Providers;

use Kyqo\Core\ServiceProvider;
use Kyqo\Http\Router\Router;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/dashboard';

    public function register(): void {}

    public function boot(): void
    {
        $router = $this->app->make(Router::class);

        // Web routes
        if (file_exists(base_path('routes/web.php'))) {
            $router->group(['middleware' => 'web'], function () {
                require base_path('routes/web.php');
            });
        }

        // API routes
        if (file_exists(base_path('routes/api.php'))) {
            $router->group(['prefix' => 'api', 'middleware' => 'api'], function () {
                require base_path('routes/api.php');
            });
        }
    }
}
