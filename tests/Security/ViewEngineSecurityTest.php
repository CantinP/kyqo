<?php

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use Kyqo\View\Engine;

class ViewEngineSecurityTest extends TestCase
{
    private Engine $engine;
    private string $viewPath;

    protected function setUp(): void
    {
        $this->viewPath = sys_get_temp_dir() . '/kyqo_views_test_' . uniqid();
        @mkdir($this->viewPath, 0700, true);
        $this->engine = new Engine([$this->viewPath]);
    }

    protected function tearDown(): void
    {
        $files = glob($this->viewPath . '/*');
        foreach ((array) $files as $file) {
            @unlink($file);
        }
        @rmdir($this->viewPath);
    }

    public function test_path_traversal_via_dotdot_is_blocked(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->engine->make('../../etc/passwd');
    }

    public function test_invalid_characters_in_template_name_are_blocked(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->engine->make('template;rm -rf /');
    }

    public function test_e_escapes_xss_output(): void
    {
        $safe = $this->engine->e('<script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $safe);
        $this->assertStringContainsString('&lt;script&gt;', $safe);
    }

    public function test_e_escapes_quotes(): void
    {
        $safe = $this->engine->e('" onclick="evil()');
        $this->assertStringContainsString('&quot;', $safe);
    }

    public function test_valid_template_is_rendered(): void
    {
        file_put_contents($this->viewPath . '/hello.php', 'hello world');
        // Use a path-key that maps to hello.php
        // Re-instantiate engine with the path
        $engine = new Engine([$this->viewPath]);
        $result = $engine->make('hello');
        $this->assertSame('hello world', $result);
    }
}
