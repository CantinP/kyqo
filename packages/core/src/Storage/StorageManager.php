<?php

namespace Kyqo\Core\Storage;

/**
 * Storage Manager
 *
 * Manages filesystem disks. Ships with a LocalDriver.
 * Usage: Storage::disk('local')->put('file.txt', 'content');
 */
class StorageManager
{
    protected array $config;
    protected array $disks = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function disk(?string $name = null): LocalDriver
    {
        $name ??= $this->config['default'] ?? 'local';

        if (!isset($this->disks[$name])) {
            $diskConfig = $this->config['disks'][$name]
                ?? throw new \InvalidArgumentException("Storage disk [{$name}] not configured.");

            $this->disks[$name] = match ($diskConfig['driver'] ?? 'local') {
                'local' => new LocalDriver($diskConfig['root'] ?? sys_get_temp_dir()),
                default => throw new \InvalidArgumentException(
                    "Storage driver [{$diskConfig['driver']}] not supported."
                ),
            };
        }

        return $this->disks[$name];
    }

    /** Proxy common calls to the default disk. */
    public function __call(string $method, array $args): mixed
    {
        return $this->disk()->$method(...$args);
    }
}
