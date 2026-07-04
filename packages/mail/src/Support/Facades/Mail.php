<?php

namespace Kyqo\Mail\Support\Facades;

use Kyqo\Core\Support\Facades\Facade;

/**
 * @method static void send(\Closure|\Kyqo\Mail\Message $message)
 * @method static \Kyqo\Mail\PendingMail to(string $address, string $name = '')
 */
class Mail extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mail';
    }
}
