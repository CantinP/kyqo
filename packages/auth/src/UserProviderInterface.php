<?php

namespace Kyqo\Auth;

/**
 * Contract for user providers.
 *
 * MINOR-V4-4: Provides a real abstraction for user retrieval so that
 * SessionGuard and TokenGuard are not permanent stubs.
 */
interface UserProviderInterface
{
    /**
     * Retrieve a user by their primary key.
     */
    public function retrieveById(mixed $id): mixed;

    /**
     * Retrieve a user by credentials (e.g. ['email' => ..., 'password' => ...]).
     * Returns the raw user record (without password verification).
     */
    public function retrieveByCredentials(array $credentials): mixed;

    /**
     * Retrieve a user by their API token.
     */
    public function retrieveByToken(string $token): mixed;

    /**
     * Return the field name used to store the plain-text password in credentials.
     */
    public function passwordField(): string;
}
