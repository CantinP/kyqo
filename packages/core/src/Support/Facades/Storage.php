<?php

namespace Kyqo\Core\Support\Facades;

/**
 * @method static \Kyqo\Core\Storage\LocalDriver disk(?string $name = null)
 * @method static bool exists(string $path)
 * @method static string get(string $path)
 * @method static bool put(string $path, string $contents)
 * @method static bool append(string $path, string $contents)
 * @method static bool delete(string $path)
 * @method static bool copy(string $from, string $to)
 * @method static bool move(string $from, string $to)
 * @method static array files(string $directory = '')
 * @method static array directories(string $directory = '')
 * @method static bool makeDirectory(string $path)
 * @method static bool deleteDirectory(string $path)
 */
class Storage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'storage';
    }
}
