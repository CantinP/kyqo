<?php

namespace Kyqo\Core\Hashing;

/**
 * Password Hasher
 *
 * Thin wrapper around PHP's password_hash / password_verify / password_needs_rehash.
 * Default algorithm: PASSWORD_BCRYPT with cost configurable via $options.
 */
class Hasher
{
    protected array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Hash a plain-text password.
     *
     * @throws \RuntimeException if hashing fails.
     */
    public function make(string $value, array $options = []): string
    {
        $merged = array_merge($this->options, $options);
        $algo   = $merged['algo'] ?? PASSWORD_BCRYPT;
        unset($merged['algo']);

        $hash = password_hash($value, $algo, $merged);

        if ($hash === false) {
            throw new \RuntimeException('Bcrypt hashing failed — check your PHP configuration.');
        }

        return $hash;
    }

    /**
     * Verify a plain-text value against a stored hash.
     */
    public function check(string $value, string $hashedValue): bool
    {
        if (strlen($hashedValue) === 0) {
            return false;
        }

        return password_verify($value, $hashedValue);
    }

    /**
     * Determine if the given hash needs to be rehashed.
     */
    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        $merged = array_merge($this->options, $options);
        $algo   = $merged['algo'] ?? PASSWORD_BCRYPT;
        unset($merged['algo']);

        return password_needs_rehash($hashedValue, $algo, $merged);
    }
}
