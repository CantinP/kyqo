<?php

namespace Kyqo\Auth\Support\Facades;

use Kyqo\Core\Support\Facades\Facade;

/**
 * @method static mixed guard(?string $name = null)
 * @method static mixed user()
 * @method static bool check()
 * @method static bool guest()
 * @method static mixed id()
 * @method static bool attempt(array $credentials, bool $remember = false)
 * @method static void login(mixed $user, bool $remember = false)
 * @method static void logout()
 */
class Auth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'auth';
    }
}
