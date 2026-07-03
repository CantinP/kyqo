<?php

namespace Kyqo\Auth\Contracts;

/**
 * Contract that any authenticatable model must satisfy.
 *
 * Models implementing this interface can be used with
 * SessionGuard and TokenGuard out of the box.
 */
interface Authenticatable
{
    /**
     * Get the unique identifier for the user (primary key).
     */
    public function getAuthIdentifier(): mixed;

    /**
     * Get the name of the unique identifier column (e.g. 'id').
     */
    public function getAuthIdentifierName(): string;

    /**
     * Get the hashed password stored for the user.
     */
    public function getAuthPassword(): string;

    /**
     * Get the token used for "remember me" sessions.
     */
    public function getRememberToken(): ?string;

    /**
     * Store a new remember-me token for the user.
     */
    public function setRememberToken(string $token): void;

    /**
     * Get the column name for the remember-me token.
     */
    public function getRememberTokenName(): string;
}
