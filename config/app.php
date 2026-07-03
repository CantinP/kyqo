<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */
    'name' => env('APP_NAME', 'Kyqo'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    */
    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    */
    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    */
    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale
    |--------------------------------------------------------------------------
    */
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    */
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        Kyqo\Core\Providers\EventServiceProvider::class,
        Kyqo\Core\Providers\LogServiceProvider::class,
        Kyqo\Core\Providers\RoutingServiceProvider::class,
        Kyqo\Database\Providers\DatabaseServiceProvider::class,
        Kyqo\Auth\Providers\AuthServiceProvider::class,
        Kyqo\Cache\Providers\CacheServiceProvider::class,
        Kyqo\Queue\Providers\QueueServiceProvider::class,
        Kyqo\Mail\Providers\MailServiceProvider::class,
        Kyqo\View\Providers\ViewServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    */
    'aliases' => [
        'App'       => Kyqo\Core\Support\Facades\App::class,
        'Auth'      => Kyqo\Auth\Support\Facades\Auth::class,
        'Cache'     => Kyqo\Cache\Support\Facades\Cache::class,
        'Config'    => Kyqo\Core\Support\Facades\Config::class,
        'DB'        => Kyqo\Database\Support\Facades\DB::class,
        'Event'     => Kyqo\Core\Support\Facades\Event::class,
        'Hash'      => Kyqo\Core\Support\Facades\Hash::class,
        'Log'       => Kyqo\Core\Support\Facades\Log::class,
        'Mail'      => Kyqo\Mail\Support\Facades\Mail::class,
        'Queue'     => Kyqo\Queue\Support\Facades\Queue::class,
        'Route'     => Kyqo\Http\Support\Facades\Route::class,
        'Session'   => Kyqo\Http\Support\Facades\Session::class,
        'Storage'   => Kyqo\Storage\Support\Facades\Storage::class,
        'View'      => Kyqo\View\Support\Facades\View::class,
        'Validator' => Kyqo\Http\Support\Facades\Validator::class,
    ],
];
