<?php

namespace Kyqo\Tests\Unit\Http;

use Kyqo\Http\Request;
use Kyqo\Http\Router\Router;
use Kyqo\Http\UrlGenerator;
use PHPUnit\Framework\TestCase;

class UrlGeneratorTest extends TestCase
{
    private function makeRequest(array $server = []): Request
    {
        $defaults = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/',
            'HTTP_HOST'      => 'example.com',
        ];
        return new Request([], [], [], array_merge($defaults, $server), [], []);
    }

    public function testToAbsoluteUrl(): void
    {
        $gen = new UrlGenerator(new Router(), $this->makeRequest());
        $this->assertSame('http://example.com/foo/bar', $gen->to('foo/bar'));
    }

    public function testToWithQuery(): void
    {
        $gen = new UrlGenerator(new Router(), $this->makeRequest());
        $url = $gen->to('/search', ['q' => 'hello world']);
        $this->assertStringContainsString('q=hello+world', $url);
    }

    public function testPreviousReturnsFallbackOnEmpty(): void
    {
        $gen = new UrlGenerator(new Router(), $this->makeRequest());
        $this->assertSame('/', $gen->previous());
    }

    public function testPreviousSanitisesJavascriptScheme(): void
    {
        $gen = new UrlGenerator(
            new Router(),
            $this->makeRequest(['HTTP_REFERER' => 'javascript:alert(1)'])
        );
        $this->assertSame('/', $gen->previous());
    }

    public function testPreviousAcceptsRelativePath(): void
    {
        $gen = new UrlGenerator(
            new Router(),
            $this->makeRequest(['HTTP_REFERER' => '/dashboard'])
        );
        $this->assertSame('/dashboard', $gen->previous());
    }

    public function testNamedRouteUrl(): void
    {
        $router = new Router();
        $router->get('/items/{id}', fn () => '')->name('items.show');
        $gen = new UrlGenerator($router, $this->makeRequest());
        $this->assertSame('http://example.com/items/7', $gen->route('items.show', ['id' => 7]));
    }
}
