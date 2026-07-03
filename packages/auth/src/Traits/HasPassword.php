<?php

namespace Kyqo\Auth\Traits;

/**
 * Provides password hashing helpers to any model.
 *
 * Requires the model to have a `password` attribute (or override $passwordAttribute).
 */
trait HasPassword
{
    protected string $passwordAttribute = 'password';

    /**
     * Get the hashed password stored for the user.
     * Satisfies Authenticatable::getAuthPassword().
     */
    public function getAuthPassword(): string
    {
        return (string) ($this->attributes[$this->passwordAttribute] ?? '');
    }

    /**
     * Hash and set the password.
     * Usage: $user->setPassword('plain_text');
     */
    public function setPassword(string $plain): void
    {
        $this->attributes[$this->passwordAttribute] = password_hash($plain, PASSWORD_BCRYPT);
    }

    /**
     * Verify a plain-text password against the stored hash.
     */
    public function checkPassword(string $plain): bool
    {
        return password_verify($plain, $this->getAuthPassword());
    }

    /**
     * Determine whether the password hash needs to be rehashed.
     */
    public function passwordNeedsRehash(): bool
    {
        return password_needs_rehash($this->getAuthPassword(), PASSWORD_BCRYPT);
    }
}
