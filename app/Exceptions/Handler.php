<?php

namespace App\Exceptions;

use Kyqo\Http\Exceptions\Handler as BaseHandler;
use Kyqo\Http\Exceptions\HttpException;

/**
 * Application Exception Handler.
 *
 * Override report() or render() here to customise error handling.
 */
class Handler extends BaseHandler
{
    protected array $dontReport = [
        HttpException::class,
        \Kyqo\Http\Validation\ValidationException::class,
    ];

    public function __construct()
    {
        parent::__construct((bool) env('APP_DEBUG', false));
    }
}
