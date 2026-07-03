<?php

use Kyqo\Core\Application;
use Kyqo\Http\Router\Router;
use Kyqo\Http\Kernel;

/*
|--------------------------------------------------------------------------
| Production Safety Guard
|--------------------------------------------------------------------------
*/

$appKey = $_ENV['APP_KEY'] ?? getenv('APP_KEY') ?? '';
$appEnv = strtolower($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'production');
$debug  = filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? false, FILTER_VALIDATE_BOOLEAN);

if (empty($appKey)) {
    http_response_code(500);
    header('Content-Type: text/plain');
    die('[Kyqo] APP_KEY is not set. Run: php kyqo key:generate');
}

$keyBytes = base64_decode(str_replace('base64:', '', $appKey), true);
if ($keyBytes === false || strlen($keyBytes) < 32) {
    http_response_code(500);
    header('Content-Type: text/plain');
    die('[Kyqo] APP_KEY is invalid or too short. Minimum 32 bytes required.');
}

if ($appEnv === 'production' && $debug === true) {
    http_response_code(500);
    header('Content-Type: text/plain');
    die('[Kyqo] APP_DEBUG must be false in production.');
}

$trustedProxies = array_filter(
    explode(',', $_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES') ?? '')
);
if (!defined('KYQO_TRUSTED_PROXIES')) {
    define('KYQO_TRUSTED_PROXIES', $trustedProxies);
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => ($appEnv === 'production'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
*/

$app = new Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| HTTP Kernel + Router
|--------------------------------------------------------------------------
*/

$app->singleton(Router::class, fn () => new Router());

$app->singleton(Kernel::class, function ($app) use ($debug) {
    return new Kernel(
        $app->make(Router::class),
        ['debug' => $debug]
    );
});

/*
|--------------------------------------------------------------------------
| Config values available via container
|--------------------------------------------------------------------------
*/

$securityConfig = file_exists(__DIR__ . '/../config/security.php')
    ? require __DIR__ . '/../config/security.php'
    : [];

$app->instance('config.security', $securityConfig);
$app->instance('config.debug',    $debug);
$app->instance('config.env',      $appEnv);

/*
|--------------------------------------------------------------------------
| Load application routes
|--------------------------------------------------------------------------
| Route Facade is now available — routes/web.php and routes/api.php
| can use Route::get(), Route::post(), etc. directly.
|--------------------------------------------------------------------------
*/

if (file_exists($routesFile = __DIR__ . '/../routes/web.php')) {
    require $routesFile;
}

if (file_exists($routesFile = __DIR__ . '/../routes/api.php')) {
    require $routesFile;
}

return $app;
