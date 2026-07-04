<?php

return [

    'name'     => env('APP_NAME', 'Kyqo'),
    'env'      => env('APP_ENV', 'production'),
    'debug'    => (bool) env('APP_DEBUG', false),
    'url'      => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'key'      => env('APP_KEY', ''),
    'cipher'   => 'AES-256-CBC',

    'providers' => [
        // Framework providers
        \Kyqo\Core\Providers\AppServiceProvider::class,
        \Kyqo\Core\Providers\EventServiceProvider::class,
        \Kyqo\Core\Providers\LogServiceProvider::class,
        \Kyqo\Database\Providers\DatabaseServiceProvider::class,
        \Kyqo\Cache\Providers\CacheServiceProvider::class,
        \Kyqo\Http\Providers\HttpServiceProvider::class,
        \Kyqo\Auth\Providers\AuthServiceProvider::class,
        \Kyqo\View\Providers\ViewServiceProvider::class,
        \Kyqo\Queue\Providers\QueueServiceProvider::class,
        \Kyqo\Mail\Providers\MailServiceProvider::class,
        \Kyqo\Console\Providers\ConsoleServiceProvider::class,

        // Application providers
        \App\Providers\AppServiceProvider::class,
        \App\Providers\AuthServiceProvider::class,
        \App\Providers\RouteServiceProvider::class,
    ],

    'aliases' => [
        'App'     => \Kyqo\Core\Support\Facades\App::class,
        'Cache'   => \Kyqo\Core\Support\Facades\Cache::class,
        'Config'  => \Kyqo\Core\Support\Facades\Config::class,
        'Event'   => \Kyqo\Core\Support\Facades\Event::class,
        'Hash'    => \Kyqo\Core\Support\Facades\Hash::class,
        'Log'     => \Kyqo\Core\Support\Facades\Log::class,
        'Storage' => \Kyqo\Core\Support\Facades\Storage::class,
        'Route'   => \Kyqo\Http\Support\Facades\Route::class,
        'Auth'    => \Kyqo\Auth\Support\Facades\Auth::class,
        'DB'      => \Kyqo\Database\Support\Facades\DB::class,
        'View'    => \Kyqo\View\Support\Facades\View::class,
        'Mail'    => \Kyqo\Mail\Support\Facades\Mail::class,
        'Queue'   => \Kyqo\Queue\Support\Facades\Queue::class,
    ],
];
