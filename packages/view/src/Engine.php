<?php

namespace Kyqo\View;

class Engine
{
    protected array $paths;
    protected string $compiledPath;
    protected bool $cache;
    protected array $sections = [];
    protected array $sectionStack = [];
    protected ?string $layout = null;
    protected array $layoutData = [];

    public function __construct(array $paths = [], string $compiledPath = '', bool $cache = true)
    {
        $this->paths = array_map(fn ($p) => realpath($p) ?: $p, $paths);
        $this->compiledPath = $compiledPath;
        $this->cache = $cache;
    }

    public function make(string $template, array $data = []): string
    {
        $path = $this->findTemplate($template);

        ob_start();
        extract($data, EXTR_SKIP);
        include $path;
        $content = (string) ob_get_clean();

        if ($this->layout) {
            $layoutPath = $this->findTemplate($this->layout);
            $this->layout = null;
            ob_start();
            extract(array_merge($data, $this->layoutData, ['content' => $content]), EXTR_SKIP);
            include $layoutPath;
            $content = (string) ob_get_clean();
        }

        return $content;
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function partial(string $template, array $data = []): string
    {
        return $this->make($template, $data);
    }

    public function extends(string $layout, array $data = []): void
    {
        $this->layout = $layout;
        $this->layoutData = $data;
    }

    public function section(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        $name = array_pop($this->sectionStack);
        $this->sections[$name] = (string) ob_get_clean();
    }

    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    protected function findTemplate(string $template): string
    {
        if (!preg_match('/^[a-zA-Z0-9._\/-]+$/', $template)) {
            throw new \InvalidArgumentException("Invalid template name [{$template}].");
        }

        if (str_contains($template, '..')) {
            throw new \RuntimeException("Path traversal detected for template [{$template}].");
        }

        $templatePath = str_replace('.', DIRECTORY_SEPARATOR, $template);
        $templatePath = preg_replace('#/+#', '/', $templatePath);

        foreach ($this->paths as $basePath) {
            $basePath = rtrim((string) $basePath, DIRECTORY_SEPARATOR);

            foreach (['.kyqo.php', '.php', '.html'] as $ext) {
                $candidate = $basePath . DIRECTORY_SEPARATOR . $templatePath . $ext;
                $real = realpath($candidate);

                if ($real === false) {
                    continue;
                }

                $allowedBase = realpath($basePath) ?: $basePath;
                if (!str_starts_with($real, $allowedBase . DIRECTORY_SEPARATOR) && $real !== $allowedBase) {
                    throw new \RuntimeException("Path traversal detected for template [{$template}].");
                }

                return $real;
            }
        }

        throw new \RuntimeException("View template [{$template}] not found.");
    }
}
