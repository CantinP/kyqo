<?php

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use Kyqo\Http\Request;

class RequestSecurityTest extends TestCase
{
    public function test_invalid_http_method_defaults_to_get(): void
    {
        $request = new Request([], [], [], ['REQUEST_METHOD' => 'FAKEMETHOD']);
        $this->assertSame('GET', $request->method());
    }

    public function test_host_header_injection_is_blocked(): void
    {
        $request = new Request([], [], [], ['HTTP_HOST' => "evil.com\r\nX-Injected: bad", 'REQUEST_URI' => '/']);
        $this->assertStringNotContainsString('\r\n', $request->url());
        $this->assertStringContainsString('localhost', $request->url());
    }

    public function test_ip_validates_format(): void
    {
        $request = new Request([], [], [], ['REMOTE_ADDR' => 'not-an-ip']);
        $this->assertNull($request->ip());
    }

    public function test_valid_ip_is_returned(): void
    {
        $request = new Request([], [], [], ['REMOTE_ADDR' => '192.168.1.1']);
        $this->assertSame('192.168.1.1', $request->ip());
    }

    public function test_bearer_token_rejects_invalid_format(): void
    {
        $request = new Request([], [], [], ['HTTP_AUTHORIZATION' => "Bearer evil\r\nX-Hdr: bad"]);
        $this->assertNull($request->bearerToken());
    }

    public function test_json_body_size_limit(): void
    {
        $this->expectException(\OverflowException::class);
        $huge    = str_repeat('a', 11 * 1024 * 1024);
        $request = new Request([], [], [], [], $huge);
        $request->json();
    }

    public function test_untrusted_x_forwarded_for_is_ignored(): void
    {
        // No trusted proxies defined, so X-Forwarded-For must be ignored
        if (defined('KYQO_TRUSTED_PROXIES')) {
            $this->markTestSkipped('KYQO_TRUSTED_PROXIES already defined.');
        }

        $request = new Request([], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
        ]);

        // Should return REMOTE_ADDR since no trusted proxies set
        $this->assertSame('10.0.0.1', $request->ip());
    }
}
