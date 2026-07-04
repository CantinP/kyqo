<?php

namespace App\Models;

use Kyqo\Database\Orm\Model;
use Kyqo\Auth\Contracts\Authenticatable;

/**
 * User model.
 *
 * Implements the Authenticatable contract so the auth system
 * can work with it out of the box.
 */
class User extends Model implements Authenticatable
{
    protected string $table    = 'users';
    protected array  $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'remember_token',
    ];
    protected array  $hidden   = ['password', 'remember_token'];
    protected array  $casts    = [
        'email_verified_at' => 'datetime',
        'password'          => 'string',
    ];

    // ---- Authenticatable interface -----------------------------------------

    public function getAuthIdentifierName(): string { return 'id'; }
    public function getAuthIdentifier(): mixed      { return $this->id; }
    public function getAuthPassword(): string       { return $this->password; }
    public function getRememberToken(): ?string     { return $this->remember_token; }
    public function setRememberToken(string $token): void { $this->remember_token = $token; }
    public function getRememberTokenName(): string  { return 'remember_token'; }
}
