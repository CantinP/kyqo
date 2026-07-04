<?php

namespace Kyqo\Http\Support\Facades;

use Kyqo\Core\Support\Facades\Facade;

/**
 * @method static \Kyqo\Http\Validation\Validator make(array $data, array $rules, array $messages = [])
 * @method static \Kyqo\Http\Validation\Validator validate(array $data, array $rules, array $messages = [])
 */
class Validator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'validator';
    }
}
