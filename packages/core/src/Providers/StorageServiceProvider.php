<?php

namespace Kyqo\Core\Providers;

use Kyqo\Core\Storage\StorageManager;

class StorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StorageManager::class, function () {
            $config = $this->app->make('config')->get('filesystems', [
                'default' => 'local',
                'disks'   => [
                    'local' => [
                        'driver' => 'local',
                        'root'   => $this->app->storagePath('app'),
                    ],
                    'public' => [
                        'driver' => 'local',
                        'root'   => $this->app->storagePath('app/public'),
                    ],
                ],
            ]);
            return new StorageManager($config);
        });
        $this->app->singleton('storage', fn () => $this->app->make(StorageManager::class));
    }
}
