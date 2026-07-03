<?php

use Kyqo\Core\Application;

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Kyqo application instance
| which serves as the "glue" for all the components of Kyqo, and is
| the IoC container for the system binding all of the various parts.
|
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
| Return The Application
|--------------------------------------------------------------------------
*/

return $app;
