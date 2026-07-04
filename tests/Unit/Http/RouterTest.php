<?php

namespace Kyqo\Tests\Unit\Http;

use Kyqo\Http\Router\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    public function testBasicGetRoute(): void
    {
        $router = new Router();
        $router->get('/hello', fn () => 'world');
        $route = $router->findRoute('GET', '/hello');
        $this->assertNotNull($route);
    }

    public function testRouteNotFound(): void
    {
        $router = new Router();
        $this->assertNull($router->findRoute('GET', '/nope'));
    }

    public function testNamedRoute(): void
    {
        $router = new Router();
        $router->get('/users/{id}', fn () => '')->name('users.show');
        $url = $router->url('users.show', ['id' => 42]);
        $this->assertSame('/users/42', $url);
    }

    public function testGroupPrefix(): void
    {
        $router = new Router();
        $router->group(['prefix' => 'api'], function (Router $r) {
            $r->get('/ping', fn () => 'pong');
        });
        $this->assertNotNull($router->findRoute('GET', '/api/ping'));
    }

    public function testResourceRoutes(): void
    {
        $router = new Router();
        $router->resource('posts', 'PostController');
        $this->assertNotNull($router->findRoute('GET',    '/posts'));
        $this->assertNotNull($router->findRoute('POST',   '/posts'));
        $this->assertNotNull($router->findRoute('GET',    '/posts/1'));
        $this->assertNotNull($router->findRoute('PUT',    '/posts/1'));
        $this->assertNotNull($router->findRoute('DELETE', '/posts/1'));
    }

    public function testMissingNamedRouteThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Router())->url('nope');
    }
}
