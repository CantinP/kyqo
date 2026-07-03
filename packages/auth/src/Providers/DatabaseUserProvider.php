<?php

namespace Kyqo\Auth\Providers;

use Kyqo\Auth\UserProviderInterface;
use Kyqo\Database\DatabaseManager;

/**
 * Retrieves users from a database table via the QueryBuilder.
 *
 * Configured via config/auth.php providers section:
 *   'users' => ['driver' => 'database', 'table' => 'users']
 *
 * Also supports Eloquent-style models (driver = 'eloquent'):
 *   'users' => ['driver' => 'eloquent', 'model' => App\Models\User::class]
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

        $password = $credentials[$this->passwordField()] ?? null;

        foreach ($credentials as $key => $value) {
            if ($key === $this->passwordField()) {
                continue;
            }
            $query->where($key, $value);
        }

        $user = $query->first();
        return $user;
    }

    public function retrieveByToken(mixed $id, string $token): mixed
    {
        $user = $this->retrieveById($id);
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
            $model?->setRememberToken(hash('sha256', $token));
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
