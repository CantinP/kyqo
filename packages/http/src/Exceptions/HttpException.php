<?php

namespace Kyqo\Http\Exceptions;

/**
 * HttpException
 *
 * Thrown when an HTTP-level error should be returned to the client.
 * The Kernel catches this and converts it to the appropriate Response.
 *
 * Usage:
 *   throw new HttpException(404, 'Page not found');
 *   abort(403, 'Forbidden');
 */
class HttpException extends \RuntimeException
{
    public function __construct(
        protected int    $statusCode,
        string           $message  = '',
        ?\Throwable      $previous = null,
        protected array  $headers  = []
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int  { return $this->statusCode; }
    public function getHeaders(): array   { return $this->headers; }
}
