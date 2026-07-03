<?php

namespace Kyqo\Auth\Providers;

use Kyqo\Auth\UserProviderInterface;
use Kyqo\Database\DatabaseManager;

/**
 * Retrieves users from a database table via the QueryBuilder.
 *
 * FIX N2: retrieveByToken() now takes a single $token parameter, matching
 * UserProviderInterface. The token is hashed with sha256 and compared
 * against the stored remember_token using hash_equals().
 *
 * Token-based API auth (TokenGuard) calls retrieveByToken($rawToken) directly.
 * Remember-me token auth (SessionGuard) is separate via updateRememberToken().
 */
class DatabaseUserProvider implements UserProviderInterface
{
    public function __construct(
        protected DatabaseManager $db,
        protected array           $config = []
    ) {}

    public function retrieveById(mixed $id): mixed
    {
        if ($this->isEloquent()) {
            return ($this->config['model'])::find($id);
        }
        return $this->db->table($this->table())->find($id);
    }

    public function retrieveByCredentials(array $credentials): mixed
    {
        $query = $this->isEloquent()
            ? ($this->config['model'])::query()
            : $this->db->table($this->table());

        foreach ($credentials as $key => $value) {
            if ($key === $this->passwordField()) {
                continue;
            }
            $query->where($key, $value);
        }

        return $query->first();
    }

    /**
     * FIX N2: single $token parameter matching UserProviderInterface.
     *
     * For API token guards: look up user by the hashed token stored in
     * the `api_token` column (configurable via config `token_column`).
     *
     * For remember-me tokens: use retrieveById() + hash_equals() instead.
     */
    public function retrieveByToken(string $token): mixed
    {
        $hashedToken = hash('sha256', $token);
        $tokenColumn = $this->config['token_column'] ?? 'api_token';

        if ($this->isEloquent()) {
            return ($this->config['model'])::where($tokenColumn, $hashedToken)->first();
        }

        return $this->db->table($this->table())
            ->where($tokenColumn, $hashedToken)
            ->first();
    }

    /**
     * Verify a remember-me token for a specific user (used by SessionGuard).
     * Separate from retrieveByToken() to keep the interface signature clean.
     */
    public function verifyRememberToken(mixed $userId, string $token): mixed
    {
        $user = $this->retrieveById($userId);
        if ($user === null) {
            return null;
        }
        $stored = is_array($user) ? ($user['remember_token'] ?? '') : ($user->remember_token ?? '');
        return hash_equals((string) $stored, hash('sha256', $token)) ? $user : null;
    }

    public function updateRememberToken(mixed $user, string $token): void
    {
        $id = is_array($user) ? ($user['id'] ?? null) : ($user->id ?? null);
        if ($id === null) {
            return;
        }
        if ($this->isEloquent()) {
            $model = ($this->config['model'])::find($id);
            $model?->setAttribute('remember_token', hash('sha256', $token));
            $model?->save();
        } else {
            $this->db->table($this->table())
                ->where('id', $id)
                ->update(['remember_token' => hash('sha256', $token)]);
        }
    }

    public function passwordField(): string
    {
        return $this->config['password_field'] ?? 'password';
    }

    protected function table(): string
    {
        return $this->config['table'] ?? 'users';
    }

    protected function isEloquent(): bool
    {
        return ($this->config['driver'] ?? '') === 'eloquent'
            && isset($this->config['model']);
    }
}
