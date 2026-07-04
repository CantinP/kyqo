<?php

namespace Kyqo\Core\Storage;

/**
 * Local filesystem driver.
 *
 * All paths are resolved relative to $root and validated against
 * path traversal via a realpath-based guard.
 *
 * FIX AUDIT-10: safePath() now uses a two-step realpath guard instead of
 * the previous naive str_contains('..') check, which could be bypassed
 * with URL-encoded sequences (%2e%2e), absolute path injection (/etc/passwd),
 * or symlink escapes:
 *
 *   Step 1 — if the target already exists on disk, its realpath() is compared
 *            against $root. A path escaping the root throws immediately.
 *
 *   Step 2 — for paths not yet on disk (new files/dirs to be created), the
 *            realpath() of the closest existing ancestor directory is checked
 *            against $root, preventing injection via yet-to-be-created paths.
 *
 * The constructor also normalises $root via realpath() so that $root itself
 * is never a raw relative or symlinked string.
 */
class LocalDriver
{
    public function __construct(protected string $root)
    {
        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }
        $this->root = rtrim(realpath($root) ?: $root, DIRECTORY_SEPARATOR);
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

    // ── Path guard ────────────────────────────────────────────────────────

    /**
     * FIX AUDIT-10: Realpath-based traversal guard.
     *
     * @throws \RuntimeException if the resolved path escapes $this->root.
     */
    protected function safePath(string $relative): string
    {
        $candidate = $this->root . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);

        // Step 1: target already exists — check its canonical path.
        $real = realpath($candidate);
        if ($real !== false) {
            if ($real !== $this->root
                && !str_starts_with($real, $this->root . DIRECTORY_SEPARATOR)
            ) {
                throw new \RuntimeException("Path traversal detected: [{$relative}]");
            }
            return $real;
        }

        // Step 2: target does not yet exist — walk up to the first existing
        // ancestor and verify it is inside $root.
        $ancestor = $candidate;
        do {
            $ancestor     = dirname($ancestor);
            $ancestorReal = realpath($ancestor);
        } while ($ancestorReal === false && $ancestor !== dirname($ancestor));

        if ($ancestorReal !== false
            && $ancestorReal !== $this->root
            && !str_starts_with($ancestorReal, $this->root . DIRECTORY_SEPARATOR)
        ) {
            throw new \RuntimeException("Path traversal detected: [{$relative}]");
        }

        return $candidate;
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
