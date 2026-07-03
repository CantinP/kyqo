<?php

namespace Kyqo\Auth\Providers;

use Kyqo\Auth\UserProviderInterface;

/**
 * In-memory user provider.
 *
 * Useful for testing and small applications.
 * Users are stored as plain arrays: ['id' => 1, 'email' => '...', 'password' => bcrypt(...)]
 *
 * MINOR-V4-4: Provides a real working implementation so guards are not stubs.
 */
class ArrayUserProvider implements UserProviderInterface
{
    protected array $users;
    protected string $passwordField;
    protected string $tokenField;

    public function __construct(
        array $users = [],
        string $passwordField = 'password',
        string $tokenField = 'api_token'
    ) {
        $this->users         = $users;
        $this->passwordField = $passwordField;
        $this->tokenField    = $tokenField;
    }

    public function retrieveById(mixed $id): mixed
    {
        foreach ($this->users as $user) {
            $userId = is_array($user) ? ($user['id'] ?? null) : ($user->id ?? null);
            if ($userId == $id) {
                return $user;
            }
        }
        return null;
    }

    public function retrieveByCredentials(array $credentials): mixed
    {
        // Match on all non-password credential fields
        $search = array_diff_key($credentials, [$this->passwordField => true]);

        foreach ($this->users as $user) {
            $match = true;
            foreach ($search as $field => $value) {
                $userValue = is_array($user) ? ($user[$field] ?? null) : ($user->$field ?? null);
                if ($userValue !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $user;
            }
        }
        return null;
    }

    public function retrieveByToken(string $token): mixed
    {
        foreach ($this->users as $user) {
            $userToken = is_array($user)
                ? ($user[$this->tokenField] ?? null)
                : ($user->{$this->tokenField} ?? null);
            if (is_string($userToken) && hash_equals($userToken, $token)) {
                return $user;
            }
        }
        return null;
    }

    public function passwordField(): string
    {
        return $this->passwordField;
    }
}
