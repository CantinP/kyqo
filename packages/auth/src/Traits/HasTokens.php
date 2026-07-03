<?php

namespace Kyqo\Auth\Traits;

/**
 * Provides API token management to any model.
 *
 * Expects `remember_token` attribute on the model (or override $rememberTokenAttribute).
 */
trait HasTokens
{
    protected string $rememberTokenAttribute = 'remember_token';

    /**
     * Satisfies Authenticatable::getRememberToken().
     */
    public function getRememberToken(): ?string
    {
        return isset($this->attributes[$this->rememberTokenAttribute])
            ? (string) $this->attributes[$this->rememberTokenAttribute]
            : null;
    }

    /**
     * Satisfies Authenticatable::setRememberToken().
     */
    public function setRememberToken(string $token): void
    {
        $this->attributes[$this->rememberTokenAttribute] = $token;
    }

    /**
     * Satisfies Authenticatable::getRememberTokenName().
     */
    public function getRememberTokenName(): string
    {
        return $this->rememberTokenAttribute;
    }

    /**
     * Generate and store a fresh cryptographically-secure token.
     * Returns the plain-text token (store it; it won't be recoverable).
     */
    public function generateToken(int $bytes = 40): string
    {
        $token = bin2hex(random_bytes($bytes));
        $this->setRememberToken(hash('sha256', $token));
        return $token;
    }

    /**
     * Verify a plain-text token against the stored hash.
     */
    public function verifyToken(string $plain): bool
    {
        $stored = $this->getRememberToken();
        if ($stored === null) {
            return false;
        }
        return hash_equals($stored, hash('sha256', $plain));
    }
}
