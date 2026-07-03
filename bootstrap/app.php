<?php

use Kyqo\Core\Application;

/*
|--------------------------------------------------------------------------
| Production Safety Guard
|--------------------------------------------------------------------------
| Ensure APP_KEY is set and has minimum length before the app boots.
| Refuse to start in production with APP_DEBUG=true.
|
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
    die('[Kyqo] APP_DEBUG must be false in production. Set APP_DEBUG=false in your .env file.');
}

// Expose trusted proxies to the rest of the framework via constant
$trustedProxies = array_filter(explode(',', $_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES') ?? ''));
if (!defined('KYQO_TRUSTED_PROXIES')) {
    define('KYQO_TRUSTED_PROXIES', $trustedProxies);
}

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $appEnv === 'production',
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
| Bind Important Interfaces
|--------------------------------------------------------------------------
*/

$app->singleton(
    Kyqo\Http\Contracts\Kernel::class,
    Kyqo\Http\Kernel::class
);

$app->singleton(
    Kyqo\Console\Contracts\Kernel::class,
    Kyqo\Console\Kernel::class
);

$app->singleton(
    Kyqo\Core\Contracts\ExceptionHandler::class,
    Kyqo\Core\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Share security config with the container
|--------------------------------------------------------------------------
*/

$app->instance('config.security', require __DIR__ . '/../config/security.php');
$app->instance('config.debug',    $debug);
$app->instance('config.env',      $appEnv);

return $app;
