<?php

namespace Kyqo\View;

/**
 * Kyqo View Engine
 *
 * Compiles and renders template files (.kyqo.php) with data injection,
 * layout support, section definitions, partials inclusion, and components.
 * Simple, fast, PHP-native template engine.
 */
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
        $this->paths        = $paths;
        $this->compiledPath = $compiledPath;
        $this->cache        = $cache;
    }

    /**
     * Render a template with data.
     */
    public function make(string $template, array $data = []): string
    {
        $path = $this->findTemplate($template);

        ob_start();
        extract($data, EXTR_SKIP);
        include $path;
        $content = ob_get_clean();

        if ($this->layout) {
            $layoutPath = $this->findTemplate($this->layout);
            $this->layout = null;
            ob_start();
            extract(array_merge($data, $this->layoutData, ['content' => $content]), EXTR_SKIP);
            include $layoutPath;
            $content = ob_get_clean();
        }

        return $content;
    }

    /**
     * Render a partial/include within a template.
     */
    public function partial(string $template, array $data = []): string
    {
        return $this->make($template, $data);
    }

    /**
     * Set the layout for the current template.
     */
    public function extends(string $layout, array $data = []): void
    {
        $this->layout     = $layout;
        $this->layoutData = $data;
    }

    /**
     * Start a named section.
     */
    public function section(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    /**
     * End a named section.
     */
    public function endSection(): void
    {
        $name = array_pop($this->sectionStack);
        $this->sections[$name] = ob_get_clean();
    }

    /**
     * Output a named section.
     */
    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Find a template file on disk.
     */
    protected function findTemplate(string $template): string
    {
        $template = str_replace('.', DIRECTORY_SEPARATOR, $template);

        foreach ($this->paths as $path) {
            foreach (['.kyqo.php', '.php', '.html'] as $ext) {
                $file = rtrim($path, '/') . '/' . $template . $ext;
                if (file_exists($file)) {
                    return $file;
                }
            }
        }

        throw new \RuntimeException("View template [{$template}] not found.");
    }
}
