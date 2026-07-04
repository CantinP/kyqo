<?php

namespace Kyqo\View\Support\Facades;

use Kyqo\Core\Support\Facades\Facade;

/**
 * @method static string make(string $template, array $data = [])
 * @method static string partial(string $template, array $data = [])
 * @method static string e(mixed $value)
 */
class View extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'view';
    }
}
