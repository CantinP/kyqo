<?php

define('KYQO_START', microtime(true));
define('KYQO_ROOT', dirname(__DIR__));

/*
|--------------------------------------------------------------------------
| Kyqo HTTP Entry Point
|--------------------------------------------------------------------------
|
| All HTTP requests come through this single file, which bootstraps the
| framework, creates the HTTP kernel, and handles the incoming request.
|
*/

if (file_exists($autoload = KYQO_ROOT.'/vendor/autoload.php')) {
    require $autoload;
}

$app = require_once KYQO_ROOT.'/bootstrap/app.php';

$kernel = $app->make(Kyqo\Http\Kernel::class);

$response = $kernel->handle(
    $request = Kyqo\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
