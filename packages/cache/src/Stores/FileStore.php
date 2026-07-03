<?php

namespace Kyqo\Cache\Stores;

use Kyqo\Cache\StoreInterface;

/**
 * File-based cache store.
 *
 * Refactor: readEntry() is now a shared private helper used by both
 * get() and has(), eliminating duplicated file-read + TTL logic and
 * ensuring the two methods are always in sync.
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
        $entry = $this->readEntry($key);
        return $entry !== null ? $entry['value'] : $default;
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $file = $this->path($key);
        $data = json_encode([
            'value'      => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
        ], JSON_THROW_ON_ERROR);

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

    /**
     * Key exists iff readEntry() returns a non-null array —
     * correctly handles null values stored intentionally.
     */
    public function has(string $key): bool
    {
        return $this->readEntry($key) !== null;
    }

    public function flush(): bool
    {
        $files = glob($this->basePath . DIRECTORY_SEPARATOR . '*.cache') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }

    /**
     * Shared TTL-aware file reader.
     *
     * Returns the decoded array on a valid, non-expired hit.
     * Returns null on missing file, unreadable file, corrupt JSON, or expiry.
     * Evicts expired entries on read (lazy expiry).
     */
    private function readEntry(string $key): ?array
    {
        $file = $this->path($key);
        if (!file_exists($file)) {
            return null;
        }

        $fh = fopen($file, 'r');
        if ($fh === false) {
            return null;
        }

        flock($fh, LOCK_SH);
        $raw = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return null;
        }

        if ($data['expires_at'] !== 0 && $data['expires_at'] <= time()) {
            @unlink($file);
            return null;
        }

        return $data;
    }

    protected function path(string $key): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.cache';
    }
}
