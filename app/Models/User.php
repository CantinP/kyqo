<?php

namespace App\Models;

use Kyqo\Auth\Contracts\Authenticatable;
use Kyqo\Auth\Traits\HasPassword;
use Kyqo\Auth\Traits\HasTokens;
use Kyqo\Database\Orm\Model;

class User extends Model implements Authenticatable
{
    use HasPassword, HasTokens;

    /**
     * The table associated with the model.
     */
    protected string $table = 'users';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected array $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected array $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];
}
