<?php

namespace Kyqo\Cache\Stores;

use Kyqo\Cache\StoreInterface;

/**
 * File-based cache store.
 *
 * Each entry is stored as a JSON file under $basePath.
 * Files are protected with 0600 permissions.
 * Read+write uses flock(LOCK_EX) to prevent race conditions.
 */
class FileStore implements StoreInterface
{
    protected string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0700, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->path($key);
        if (!file_exists($file)) {
            return $default;
        }

        $fh = fopen($file, 'r');
        if ($fh === false) {
            return $default;
        }

        flock($fh, LOCK_SH);
        $raw = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return $default;
        }

        if ($data['expires_at'] !== 0 && $data['expires_at'] <= time()) {
            @unlink($file);
            return $default;
        }

        return $data['value'];
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $file = $this->path($key);
        $data = json_encode([
            'value'      => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
        ], JSON_THROW_ON_ERROR);

        // SEC: set restrictive umask before creating the file
        $oldUmask = umask(0077);
        $fh = fopen($file, 'c+');
        umask($oldUmask);

        if ($fh === false) {
            return false;
        }

        flock($fh, LOCK_EX);
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, $data);
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        chmod($file, 0600);
        return true;
    }

    public function forget(string $key): bool
    {
        $file = $this->path($key);
        return file_exists($file) ? (bool) @unlink($file) : true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function flush(): bool
    {
        $files = glob($this->basePath . DIRECTORY_SEPARATOR . '*.cache') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }

    protected function path(string $key): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.cache';
    }
}
