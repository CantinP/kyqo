<?php

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use Kyqo\Http\Response;

class ResponseSecurityTest extends TestCase
{
    public function test_crlf_injection_in_header_value_is_stripped(): void
    {
        $response = Response::make('ok');
        $response->setHeader('X-Test', "value\r\nX-Injected: evil");
        $this->assertSame('valueX-Injected: evil', $response->getHeader('X-Test'));
    }

    public function test_crlf_injection_in_header_key_is_stripped(): void
    {
        $response = Response::make('ok');
        $response->setHeader("X-Test\r\nX-Evil", 'bad');
        // The key should have CRLF stripped
        $this->assertNull($response->getHeader('X-Test'));
        $headers = $response->headers();
        foreach (array_keys($headers) as $key) {
            $this->assertStringNotContainsString("\r", $key);
            $this->assertStringNotContainsString("\n", $key);
        }
    }

    public function test_javascript_redirect_is_blocked(): void
    {
        $response = Response::redirect('javascript:alert(1)');
        $this->assertSame('/', $response->getHeader('Location'));
    }

    public function test_data_uri_redirect_is_blocked(): void
    {
        $response = Response::redirect('data:text/html,<script>alert(1)</script>');
        $this->assertSame('/', $response->getHeader('Location'));
    }

    public function test_relative_redirect_is_allowed(): void
    {
        $response = Response::redirect('/dashboard');
        $this->assertSame('/dashboard', $response->getHeader('Location'));
    }

    public function test_https_redirect_is_allowed(): void
    {
        $response = Response::redirect('https://kyqo.dev/dashboard');
        $this->assertSame('https://kyqo.dev/dashboard', $response->getHeader('Location'));
    }
}
