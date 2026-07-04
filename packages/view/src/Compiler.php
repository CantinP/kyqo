<?php

namespace Kyqo\View;

/**
 * Kyqo View Compiler
 *
 * Compiles .kyqo.php templates with @-directives into plain PHP files
 * that are cached in the compiled directory.
 *
 * Supported directives:
 *   @extends('layout')          @section('name') @endsection
 *   @yield('name', 'default')   @include('partial', [...data])
 *   @if(...) @elseif(...) @else @endif
 *   @foreach($arr as $item)     @endforeach
 *   @for(...) @endfor           @while(...) @endwhile
 *   @forelse($arr as $item)     @empty @endforelse
 *   @isset($var) @endisset      @empty($var) @endempty
 *   @switch($var) @case(x) @default @endswitch
 *   @auth @guest @endauth @endguest
 *   @csrf  @method('PUT')
 *   @dump(...) @dd(...)
 *
 * Output syntax:
 *   {{ $expr }}    — HTML-escaped output (htmlspecialchars, ENT_QUOTES|ENT_SUBSTITUTE)
 *   {!! $expr !!}  — RAW unescaped output.
 *                    ⚠️  SECURITY: the developer is solely responsible for
 *                    ensuring $expr is safe before using {!! !!}. Never pass
 *                    unsanitised user input through raw output directives or
 *                    an XSS vulnerability will result.
 *   {{-- comment --}} — removed at compile time
 *
 * FIX AUDIT-2: compileDirectives() now detects preg_replace() failures
 *              (PREG_BACKTRACK_LIMIT_ERROR, malformed pattern in @-directives,
 *              etc.) and throws a \RuntimeException with the offending pattern
 *              and PREG error name instead of silently returning null.
 */
class Compiler
{
    public function __construct(
        protected string $compiledPath,
        protected bool   $cache = true
    ) {
        if (!is_dir($this->compiledPath)) {
            @mkdir($this->compiledPath, 0755, true);
        }
    }

    public function getCompiledPath(string $sourcePath): string
    {
        return $this->compiledPath . '/' . sha1($sourcePath) . '.php';
    }

    public function isExpired(string $sourcePath): bool
    {
        if (!$this->cache) return true;
        $compiled = $this->getCompiledPath($sourcePath);
        if (!file_exists($compiled)) return true;
        return filemtime($sourcePath) > filemtime($compiled);
    }

    public function compile(string $sourcePath): string
    {
        $content  = file_get_contents($sourcePath);
        $compiled = $this->compileString($content);
        $dest     = $this->getCompiledPath($sourcePath);
        file_put_contents($dest, $compiled);
        return $dest;
    }

    public function compileString(string $content): string
    {
        // Remove comments
        $content = $this->safeReplace('/\{\{--.*?--\}\}/s', '', $content, '{{-- --}}');

        // Raw output {!! ... !!}
        // ⚠️  No escaping — see class-level PHPDoc for security note.
        $content = $this->safeReplace('/\{!!\s*(.+?)\s*!!\}/s', '<?php echo $1; ?>', $content, '{!! !!}');

        // Escaped output {{ ... }}
        $content = $this->safeReplace(
            '/\{\{\s*(.+?)\s*\}\}/s',
            '<?php echo htmlspecialchars($1, ENT_QUOTES|ENT_SUBSTITUTE, \'UTF-8\'); ?>',
            $content,
            '{{ }}'
        );

        // @-directives
        $content = $this->compileDirectives($content);

        return $content;
    }

    protected function compileDirectives(string $content): string
    {
        $patterns = [
            // Layout & sections
            '/@extends\((.+?)\)/'                  => '<?php $kyqoEngine->extends($1); ?>',
            '/@section\(([\'"])(\w+)\1\)/'          => '<?php $kyqoEngine->section($2); ?>',
            '/@endsection/'                         => '<?php $kyqoEngine->endSection(); ?>',
            '/@yield\((.+?)\)/'                     => '<?php echo $kyqoEngine->yield($1); ?>',
            '/@include\((.+?)\)/'                   => '<?php echo $kyqoEngine->partial($1); ?>',

            // Control structures
            '/@if\((.+?)\)/'                        => '<?php if ($1): ?>',
            '/@elseif\((.+?)\)/'                    => '<?php elseif ($1): ?>',
            '/@else/'                               => '<?php else: ?>',
            '/@endif/'                              => '<?php endif; ?>',

            '/@foreach\((.+?)\)/'                   => '<?php foreach ($1): ?>',
            '/@endforeach/'                         => '<?php endforeach; ?>',

            '/@for\((.+?)\)/'                       => '<?php for ($1): ?>',
            '/@endfor/'                             => '<?php endfor; ?>',

            '/@while\((.+?)\)/'                     => '<?php while ($1): ?>',
            '/@endwhile/'                           => '<?php endwhile; ?>',

            // forelse — @empty (bare) must come AFTER @empty($var) to avoid mis-match
            '/@forelse\((.+?)\)/'                   => '<?php $__forelseArr = $1; if (!empty($__forelseArr)): foreach ($__forelseArr as $__forelseKey => $__forelseVal): ?>',
            '/@endforelse/'                         => '<?php endif; ?>',

            // isset / empty with argument — FIX D1: these MUST appear before bare @empty
            '/@isset\((.+?)\)/'                     => '<?php if (isset($1)): ?>',
            '/@endisset/'                           => '<?php endif; ?>',
            '/@empty\((.+?)\)/'                     => '<?php if (empty($1)): ?>',
            '/@endempty/'                           => '<?php endif; ?>',

            // FIX D1: bare @empty (used inside @forelse) AFTER @empty($var)
            '/@empty/'                              => '<?php endforeach; else: ?>',

            // switch
            '/@switch\((.+?)\)/'                    => '<?php switch ($1): ?>',
            '/@case\((.+?)\)/'                      => '<?php case $1: ?>',
            '/@default/'                            => '<?php default: ?>',
            '/@endswitch/'                          => '<?php endswitch; ?>',
            '/@break/'                              => '<?php break; ?>',

            // Auth
            '/@auth/'                               => '<?php if (auth()->check()): ?>',
            '/@endauth/'                            => '<?php endif; ?>',
            '/@guest/'                              => '<?php if (!auth()->check()): ?>',
            '/@endguest/'                           => '<?php endif; ?>',

            // CSRF / method spoofing
            '/@csrf/'                               => '<?php echo csrf_field(); ?>',
            '/@method\(([\'"])(\w+)\2\)/'            => '<?php echo method_field($2); ?>',

            // Debug
            '/@dump\((.+?)\)/'                      => '<?php var_dump($1); ?>',
            '/@dd\((.+?)\)/'                        => '<?php var_dump($1); exit(1); ?>',

            // PHP blocks
            '/@php/'                                => '<?php',
            '/@endphp/'                             => '?>',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $content = $this->safeReplace($pattern, $replacement, $content, $pattern);
        }

        return $content;
    }

    /**
     * FIX AUDIT-2: Wrapper around preg_replace() that throws on failure.
     *
     * preg_replace() returns null on error (PREG_BACKTRACK_LIMIT_ERROR, etc.)
     * and PHP only sets preg_last_error() — the caller would silently receive
     * null and likely produce an empty/corrupt compiled file.
     *
     * @throws \RuntimeException with pattern name and PREG error constant name.
     */
    protected function safeReplace(string $pattern, string $replacement, string $subject, string $label): string
    {
        $result = preg_replace($pattern, $replacement, $subject);

        if ($result === null) {
            $errorCode = preg_last_error();
            $errorName = array_flip(get_defined_constants(true)['pcre'] ?? [])[$errorCode] ?? "PREG_ERROR_{$errorCode}";
            throw new \RuntimeException(
                "Kyqo\\View\\Compiler: preg_replace failed on pattern [{$label}]. "
                . "PCRE error: {$errorName} (code {$errorCode})."
            );
        }

        return $result;
    }
}
