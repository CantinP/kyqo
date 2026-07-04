<?php

namespace App\Models;

use Kyqo\Database\Orm\Model;

/**
 * User Model
 *
 * @property int         $id
 * @property string      $name
 * @property string      $email
 * @property string      $password
 * @property string|null $remember_token
 * @property string      $created_at
 * @property string      $updated_at
 */
class User extends Model
{
    protected string $table = 'users';

    /**
     * Mass-assignable attributes.
     */
    protected array $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Attributes hidden from serialization (toArray / toJson).
     */
    protected array $hidden = [
        'password',
        'remember_token',
    ];
}
