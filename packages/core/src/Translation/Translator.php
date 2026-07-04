<?php

namespace Kyqo\Core\Translation;

/**
 * Translator — file-based i18n with full pluralisation support.
 *
 * BASIC USAGE
 * ──────────────────────────────────────────────────────────────────────────
 *   trans('auth.failed')                        // simple key
 *   trans('auth.welcome', ['name' => 'Alice'])  // with :placeholder
 *   __('auth.failed')                           // alias
 *   lang('auth.failed')                         // alias
 *
 * PLURALISATION — trans_choice() / choice()
 * ──────────────────────────────────────────────────────────────────────────
 * Three syntaxes are supported and can be mixed:
 *
 * 1. Pipe with explicit counts   {0} Aucun|{1} Un|[2,*] :count résultats
 * 2. Pipe simple (two forms)     :count apple|:count apples
 * 3. ICU-style                   {count, plural, =0{none} one{# item} other{# items}}
 *
 * Examples:
 *   // resources/lang/fr/messages.php
 *   return [
 *       'apples' => '{0} Pas de pomme|{1} Une pomme|[2,*] :count pommes',
 *       'items'  => ':count item|:count items',
 *       'files'  => '{count, plural, =0{Aucun fichier} one{# fichier} other{# fichiers}}',
 *   ];
 *
 *   trans_choice('messages.apples', 0)           // 'Pas de pomme'
 *   trans_choice('messages.apples', 1)           // 'Une pomme'
 *   trans_choice('messages.apples', 5)           // '5 pommes'
 *   trans_choice('messages.items', 3)            // '3 items'
 *   trans_choice('messages.files', 0)            // 'Aucun fichier'
 *   trans_choice('messages.files', 1)            // '1 fichier'
 *   trans_choice('messages.files', 42)           // '42 fichiers'
 */
class Translator
{
    private array  $loaded   = [];
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

    // ── Locale ────────────────────────────────────────────────────────────

    public function getLocale(): string   { return $this->locale; }
    public function getFallback(): string { return $this->fallback; }
    public function setLocale(string $locale): void { $this->locale = $locale; }

    // ── Simple translation ────────────────────────────────────────────────

    /**
     * Get the translation for a key.
     *
     * @param string      $key     Dot-notation: 'file.key'
     * @param array       $replace Placeholder map: ['name' => 'Alice']
     * @param string|null $locale  Override locale for this call only
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
     * Get a pluralised translation string.
     *
     * @param string      $key      Dot-notation translation key
     * @param int|float   $number   The count used to select the plural form
     * @param array       $replace  Additional placeholders; :count is always added
     * @param string|null $locale   Override locale for this call only
     */
    public function choice(string $key, int|float $number, array $replace = [], ?string $locale = null): string
    {
        $locale ??= $this->locale;

        $raw = $this->resolve($key, $locale)
            ?? $this->resolve($key, $this->fallback)
            ?? $key;

        $line = $this->selectPlural((string) $raw, $number);

        $replace = array_merge(['count' => $number], $replace);

        return $this->applyReplacements($line, $replace);
    }

    /**
     * Check whether a translation key exists.
     */
    public function has(string $key, ?string $locale = null): bool
    {
        return $this->resolve($key, $locale ?? $this->locale) !== null;
    }

    /**
     * Get all translations for a file.
     */
    public function all(string $file, ?string $locale = null): array
    {
        $locale ??= $this->locale;
        $this->load($file, $locale);
        return $this->loaded[$locale][$file] ?? [];
    }

    // ── Plural selector ───────────────────────────────────────────────────

    /**
     * Select the correct plural form from a raw translation string.
     *
     * Supported formats (tried in order):
     *
     * 1. ICU-style:
     *      {count, plural, =0{none} one{# item} other{# items}}
     *
     * 2. Pipe with interval/exact prefixes:
     *      {0} None|{1} One|[2,*] :count items
     *      {0} None|[1,*] :count items
     *
     * 3. Pipe — two forms (singular | plural):
     *      :count apple|:count apples
     */
    private function selectPlural(string $raw, int|float $number): string
    {
        // 1. ICU-style: {var, plural, =N{…} one{…} other{…}}
        if (preg_match('/^\{\w+,\s*plural,(.+)\}$/su', $raw, $m)) {
            return $this->selectIcu($m[1], $number);
        }

        // 2 & 3. Pipe-separated forms
        if (str_contains($raw, '|')) {
            return $this->selectPipe($raw, $number);
        }

        return $raw;
    }

    /**
     * ICU plural selector.
     *
     * Handles:  =N{…}  one{…}  few{…}  many{…}  other{…}
     * The '#' inside a form is replaced by the number.
     */
    private function selectIcu(string $body, int|float $number): string
    {
        // Extract all forms: keyword{ content }
        preg_match_all('/(=\d+|zero|one|two|few|many|other)\{([^}]*)\}/u', $body, $matches, PREG_SET_ORDER);

        $exact    = '=' . $number;
        $category = $this->pluralCategory((int) $number);
        $fallback = null;

        foreach ($matches as $match) {
            $keyword = $match[1];
            $content = str_replace('#', (string) $number, $match[2]);

            if ($keyword === $exact)    return $content;
            if ($keyword === $category) return $content;
            if ($keyword === 'other')   $fallback = $content;
        }

        return $fallback ?? (string) $number;
    }

    /**
     * Pipe-separated plural selector.
     *
     * Each segment may be prefixed with:
     *   {N}       — exact match
     *   [N,M]     — range, inclusive; * means infinity
     *   [N,*]     — N or more
     *   No prefix — positional: first = singular, second = plural
     */
    private function selectPipe(string $raw, int|float $number): string
    {
        $segments  = array_map('trim', explode('|', $raw));
        $positional = [];

        foreach ($segments as $segment) {
            // Exact match: {N}
            if (preg_match('/^\{(\d+)\}(.*)$/su', $segment, $m)) {
                if ((int) $m[1] === (int) $number) return trim($m[2]);
                continue;
            }

            // Range: [N,M] or [N,*]
            if (preg_match('/^\[(\d+),(\d+|\*)\](.*)$/su', $segment, $m)) {
                $lo  = (int) $m[1];
                $hi  = $m[2] === '*' ? PHP_INT_MAX : (int) $m[2];
                if ($number >= $lo && $number <= $hi) return trim($m[3]);
                continue;
            }

            // No prefix — collect positional
            $positional[] = $segment;
        }

        // Positional fallback: two forms → [singular, plural]
        if (count($positional) === 2) {
            return $number == 1 ? $positional[0] : $positional[1];
        }

        // More than two positional forms — pick by index clamped to last
        if (!empty($positional)) {
            $idx = min((int) $number, count($positional) - 1);
            return $positional[max(0, $idx)];
        }

        return $raw;
    }

    /**
     * Map an integer count to a CLDR plural category.
     * Covers English, French, German, Spanish, Italian (and most others).
     * For complex languages (Arabic, Russian, Polish…) extend this method.
     */
    private function pluralCategory(int $n): string
    {
        $abs = abs($n);
        return match (true) {
            $abs === 0                 => 'zero',
            $abs === 1                 => 'one',
            $abs === 2                 => 'two',
            $abs >= 3 && $abs <= 6     => 'few',
            $abs >= 7 && $abs <= 10    => 'many',
            default                    => 'other',
        };
    }

    // ── Internal ──────────────────────────────────────────────────────────

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

    private function parseKey(string $key): array
    {
        $dot = strpos($key, '.');
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
                [(string) $value, strtoupper((string) $value), ucfirst((string) $value)],
                $line
            );
        }

        return $line;
    }
}
