<?php

namespace Kyqo\Core\Storage;

/**
 * Local filesystem driver.
 *
 * All paths are resolved relative to $root and validated
 * against path traversal via realpath comparison.
 */
class LocalDriver
{
    public function __construct(protected string $root)
    {
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
        if (!is_dir($this->root)) {
            mkdir($this->root, 0755, true);
        }
    }

    public function path(string $path): string
    {
        return $this->root . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    public function exists(string $path): bool
    {
        return file_exists($this->safePath($path));
    }

    public function get(string $path): string
    {
        $safe = $this->safePath($path);
        if (!file_exists($safe)) {
            throw new \RuntimeException("File not found: [{$path}]");
        }
        return file_get_contents($safe);
    }

    public function put(string $path, string $contents): bool
    {
        $safe = $this->safePath($path);
        $this->ensureDirectory(dirname($safe));
        $oldUmask = umask(0022);
        $result   = file_put_contents($safe, $contents, LOCK_EX) !== false;
        umask($oldUmask);
        return $result;
    }

    public function append(string $path, string $contents): bool
    {
        $safe = $this->safePath($path);
        $this->ensureDirectory(dirname($safe));
        return file_put_contents($safe, $contents, FILE_APPEND | LOCK_EX) !== false;
    }

    public function delete(string $path): bool
    {
        $safe = $this->safePath($path);
        return file_exists($safe) ? unlink($safe) : true;
    }

    public function copy(string $from, string $to): bool
    {
        $src  = $this->safePath($from);
        $dest = $this->safePath($to);
        $this->ensureDirectory(dirname($dest));
        return copy($src, $dest);
    }

    public function move(string $from, string $to): bool
    {
        $src  = $this->safePath($from);
        $dest = $this->safePath($to);
        $this->ensureDirectory(dirname($dest));
        return rename($src, $dest);
    }

    public function size(string $path): int
    {
        return (int) filesize($this->safePath($path));
    }

    public function lastModified(string $path): int
    {
        return (int) filemtime($this->safePath($path));
    }

    public function files(string $directory = ''): array
    {
        $dir   = $this->safePath($directory);
        $files = [];
        if (!is_dir($dir)) return $files;
        foreach (new \DirectoryIterator($dir) as $item) {
            if ($item->isFile()) {
                $files[] = $item->getFilename();
            }
        }
        return $files;
    }

    public function directories(string $directory = ''): array
    {
        $dir  = $this->safePath($directory);
        $dirs = [];
        if (!is_dir($dir)) return $dirs;
        foreach (new \DirectoryIterator($dir) as $item) {
            if ($item->isDir() && !$item->isDot()) {
                $dirs[] = $item->getFilename();
            }
        }
        return $dirs;
    }

    public function makeDirectory(string $path): bool
    {
        $safe = $this->safePath($path);
        if (is_dir($safe)) return true;
        return mkdir($safe, 0755, true);
    }

    public function deleteDirectory(string $path): bool
    {
        $safe = $this->safePath($path);
        if (!is_dir($safe)) return true;
        $this->rrmdir($safe);
        return true;
    }

    protected function safePath(string $relative): string
    {
        if (str_contains($relative, '..')) {
            throw new \RuntimeException("Path traversal detected: [{$relative}]");
        }
        return $this->root . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
    }

    protected function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function rrmdir(string $dir): void
    {
        foreach (new \FilesystemIterator($dir) as $item) {
            $item->isDir() ? $this->rrmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
