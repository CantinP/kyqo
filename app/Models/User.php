<?php

namespace App\Models;

use Kyqo\Auth\Contracts\Authenticatable;
use Kyqo\Auth\Traits\HasPassword;
use Kyqo\Auth\Traits\HasTokens;
use Kyqo\Database\Orm\Model;

class User extends Model implements Authenticatable
{
    use HasPassword, HasTokens;

    protected string $table = 'users';

    protected array $fillable = [
        'name',
        'email',
        'password',
    ];

    protected array $hidden = [
        'password',
        'remember_token',
    ];

    protected array $casts = [
        'email_verified_at' => 'datetime',
    ];

    // ---- Authenticatable implementation ------------------------------------

    public function getAuthIdentifier(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public function getAuthIdentifierName(): string
    {
        return $this->primaryKey;
    }
}
