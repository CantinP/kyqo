<?php

namespace Kyqo\Testing;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Kyqo\Core\Application;
use Kyqo\Http\Request;
use Kyqo\Http\Response;
use Kyqo\Http\Kernel;

/**
 * Base TestCase with HTTP request helpers.
 *
 * Usage:
 *
 *   class PostControllerTest extends TestCase
 *   {
 *       public function test_can_list_posts(): void
 *       {
 *           $response = $this->get('/posts');
 *           $response->assertStatus(200)
 *                    ->assertJsonContains(['data']);
 *       }
 *   }
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = $this->createApplication();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Override to customise the application instance.
     */
    protected function createApplication(): Application
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $app      = new Application($basePath);
        $app->bootstrap();
        return $app;
    }

    // ── HTTP helpers ─────────────────────────────────────────────────────

    public function get(string $uri, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, [], $headers);
    }

    public function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, $data, $headers);
    }

    public function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PUT', $uri, $data, $headers);
    }

    public function patch(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PATCH', $uri, $data, $headers);
    }

    public function delete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('DELETE', $uri, $data, $headers);
    }

    public function getJson(string $uri, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, [], array_merge(['Accept' => 'application/json'], $headers));
    }

    public function postJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, $data, array_merge(
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $headers
        ));
    }

    public function putJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PUT', $uri, $data, array_merge(
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $headers
        ));
    }

    public function patchJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PATCH', $uri, $data, array_merge(
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $headers
        ));
    }

    public function deleteJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('DELETE', $uri, $data, array_merge(
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $headers
        ));
    }

    // ── Auth helpers ─────────────────────────────────────────────────────

    public function actingAs(object $user, string $guard = 'session'): static
    {
        // Store the user in the session to simulate auth
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $_SESSION['_auth_user_' . $guard] = serialize($user);
        return $this;
    }

    public function withoutCsrf(): static
    {
        // Bypass CSRF by injecting a matching token
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $_SESSION['_kyqo_csrf_token'] = 'test_csrf_token';
        return $this;
    }

    // ── Core dispatcher ─────────────────────────────────────────────────

    public function call(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $request  = $this->buildRequest($method, $uri, $data, $headers);
        $kernel   = $this->app->make(Kernel::class);
        $response = $kernel->handle($request);
        return new TestResponse($response);
    }

    protected function buildRequest(string $method, string $uri, array $data, array $headers): Request
    {
        $isJson = isset($headers['Content-Type']) && str_contains($headers['Content-Type'], 'json');

        // Parse URI
        $parsedUrl   = parse_url($uri);
        $path        = $parsedUrl['path'] ?? '/';
        $queryString = $parsedUrl['query'] ?? '';

        // Build $_SERVER superglobal substitute
        $server = [
            'REQUEST_METHOD'  => strtoupper($method),
            'REQUEST_URI'     => $uri,
            'PATH_INFO'       => $path,
            'QUERY_STRING'    => $queryString,
            'HTTP_HOST'       => 'localhost',
            'HTTP_ACCEPT'     => $headers['Accept'] ?? 'text/html',
            'CONTENT_TYPE'    => $headers['Content-Type'] ?? '',
            'HTTPS'           => 'off',
        ];

        foreach ($headers as $k => $v) {
            $key          = 'HTTP_' . strtoupper(str_replace('-', '_', $k));
            $server[$key] = $v;
        }

        if ($isJson && !empty($data)) {
            $body = json_encode($data);
        } elseif (!empty($data)) {
            $body = http_build_query($data);
        } else {
            $body = '';
        }

        // Inject CSRF token for mutating requests
        if (in_array(strtoupper($method), ['POST','PUT','PATCH','DELETE'], true) && !$isJson) {
            if (session_status() === PHP_SESSION_NONE) @session_start();
            $token = $_SESSION['_kyqo_csrf_token'] ?? 'test_csrf_token';
            if (!$isJson && !empty($data)) {
                parse_str($body, $formData);
                $formData['_token'] = $token;
                $body = http_build_query($formData);
            }
        }

        $request = new Request(
            method:  strtoupper($method),
            uri:     $path,
            headers: $headers,
            body:    $body,
            query:   array_merge([], ...(!empty($queryString) ? [array_filter(explode('&', $queryString), fn($s) => str_contains($s, '='))] : [[]])),
            post:    !$isJson ? $data : [],
            server:  $server
        );

        if ($isJson && !empty($data)) {
            $request->setJsonPayload($data);
        }

        return $request;
    }
}
