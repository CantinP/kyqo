<?php

namespace Kyqo\Http\Validation;

/**
 * Thrown when validation fails.
 * Carries the full errors bag so controllers/middleware can handle it.
 */
class ValidationException extends \RuntimeException
{
    public function __construct(
        protected array $errors,
        string $message = 'The given data was invalid.',
        int $code = 422
    ) {
        parent::__construct($message, $code);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
