<?php

namespace Kyqo\Database\Factories;

/**
 * Ensures generated values are unique across calls.
 *
 * Usage:  $this->fake()->unique()->email()
 */
class UniqueGenerator
{
    private array $generated = [];

    public function __construct(private FakeData $fake) {}

    public function __call(string $method, array $args): mixed
    {
        $maxRetries = 100;
        for ($i = 0; $i < $maxRetries; $i++) {
            $value = $this->fake->{$method}(...$args);
            $key   = $method . ':' . (is_scalar($value) ? $value : serialize($value));
            if (!isset($this->generated[$key])) {
                $this->generated[$key] = true;
                return $value;
            }
        }
        throw new \OverflowException(
            "FakeData::unique()->{$method}() could not generate a unique value after {$maxRetries} attempts."
        );
    }
}
