<?php

namespace Kyqo\Http\Exceptions;

class MethodNotAllowedHttpException extends HttpException
{
    public function __construct(array $allowed = [], ?\Throwable $previous = null)
    {
        parent::__construct(405, 'Method Not Allowed', $previous, ['Allow' => implode(', ', $allowed)]);
    }
}
