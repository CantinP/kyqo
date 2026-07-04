<?php

namespace Kyqo\Core\Translation;

/**
 * Translator — file-based internationalisation.
 *
 * Language files are PHP arrays stored in:
 *   resources/lang/{locale}/{file}.php
 *
 * Usage:
 *   // resources/lang/fr/auth.php
 *   return ['failed' => 'Ces identifiants ne correspondent pas.'];
 *
 *   trans('auth.failed');          // 'Ces identifiants ne correspondent pas.'
 *   __('auth.failed');             // same
 *   trans('auth.welcome', ['name' => 'Alice']); // with replacements
 *
 * Fallback chain: locale → fallback locale → key itself.
 */
class Translator
{
    private array  $loaded = [];
    private string $locale;
    private string $fallback;
    private string $langPath;

    public function __construct(
        string $langPath,
        string $locale   = 'en',
        string $fallback = 'en'
    ) {
        $this->langPath = rtrim($langPath, '/');
        $this->locale   = $locale;
        $this->fallback = $fallback;
    }

    // ── Locale ─────────────────────────────────────────────────────────────

    public function getLocale(): string  { return $this->locale; }
    public function getFallback(): string { return $this->fallback; }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    // ── Translation ─────────────────────────────────────────────────────────

    /**
     * Get the translation for a given key.
     *
     * @param  string   $key      Dot-notation: 'file.key' or 'file.group.key'
     * @param  array    $replace  Placeholder replacements: ['name' => 'Alice']
     * @param  string|null $locale Override the current locale for this call
     * @return string
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= $this->locale;

        $value = $this->resolve($key, $locale)
              ?? $this->resolve($key, $this->fallback)
              ?? $key;

        return $this->applyReplacements((string) $value, $replace);
    }

    /**
     * Check whether a translation key exists.
     */
    public function has(string $key, ?string $locale = null): bool
    {
        return $this->resolve($key, $locale ?? $this->locale) !== null;
    }

    /**
     * Get all translations for a given file / namespace.
     */
    public function all(string $file, ?string $locale = null): array
    {
        $locale ??= $this->locale;
        $this->load($file, $locale);
        return $this->loaded[$locale][$file] ?? [];
    }

    // ── Internal ────────────────────────────────────────────────────────────

    private function resolve(string $key, string $locale): mixed
    {
        [$file, $dotKey] = $this->parseKey($key);

        $this->load($file, $locale);

        $lines = $this->loaded[$locale][$file] ?? [];

        if ($dotKey === '') return null;

        $value = $lines;
        foreach (explode('.', $dotKey) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : null;
    }

    /**
     * Parse 'file.key' into ['file', 'key'].
     * Supports both 'auth.failed' and 'validation.custom.email.required'.
     */
    private function parseKey(string $key): array
    {
        $dot  = strpos($key, '.');
        if ($dot === false) return [$key, ''];
        return [substr($key, 0, $dot), substr($key, $dot + 1)];
    }

    private function load(string $file, string $locale): void
    {
        if (isset($this->loaded[$locale][$file])) return;

        $path = $this->langPath . '/' . $locale . '/' . $file . '.php';

        if (!file_exists($path)) {
            $this->loaded[$locale][$file] = [];
            return;
        }

        $lines = include $path;
        $this->loaded[$locale][$file] = is_array($lines) ? $lines : [];
    }

    private function applyReplacements(string $line, array $replace): string
    {
        if (empty($replace)) return $line;

        foreach ($replace as $key => $value) {
            $line = str_replace(
                [':' . $key, ':' . strtoupper($key), ':' . ucfirst($key)],
                [$value,     strtoupper((string) $value), ucfirst((string) $value)],
                $line
            );
        }

        return $line;
    }
}
