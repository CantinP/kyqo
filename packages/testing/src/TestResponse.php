<?php

namespace Kyqo\Testing;

use Kyqo\Http\Response;
use PHPUnit\Framework\Assert;

/**
 * Wraps a Response and provides assertion helpers.
 *
 * Usage:
 *   $this->get('/users')
 *        ->assertStatus(200)
 *        ->assertJson(['data' => []])
 *        ->assertSee('Alice');
 */
class TestResponse
{
    private array $decodedJson;

    public function __construct(private Response $response) {}

    public function getResponse(): Response { return $this->response; }
    public function getStatusCode(): int    { return $this->response->getStatusCode(); }
    public function getContent(): string    { return $this->response->getContent(); }
    public function getHeaders(): array     { return $this->response->getHeaders(); }

    // ── Status ─────────────────────────────────────────────────────────────

    public function assertStatus(int $status): static
    {
        Assert::assertEquals(
            $status,
            $this->getStatusCode(),
            "Expected HTTP status {$status}, got {$this->getStatusCode()}. Body: " . substr($this->getContent(), 0, 500)
        );
        return $this;
    }

    public function assertOk(): static    { return $this->assertStatus(200); }
    public function assertCreated(): static { return $this->assertStatus(201); }
    public function assertNoContent(): static { return $this->assertStatus(204); }
    public function assertNotFound(): static  { return $this->assertStatus(404); }
    public function assertForbidden(): static  { return $this->assertStatus(403); }
    public function assertUnauthorized(): static { return $this->assertStatus(401); }
    public function assertUnprocessable(): static { return $this->assertStatus(422); }
    public function assertRedirect(string $url = null): static
    {
        Assert::assertContains($this->getStatusCode(), [301, 302, 303, 307, 308]);
        if ($url !== null) {
            $location = $this->response->getHeader('Location') ?? '';
            Assert::assertEquals($url, $location, "Expected redirect to [{$url}], got [{$location}].");
        }
        return $this;
    }

    // ── Content ───────────────────────────────────────────────────────────

    public function assertSee(string $text): static
    {
        Assert::assertStringContainsString(
            $text,
            $this->getContent(),
            "Response does not contain [{$text}]."
        );
        return $this;
    }

    public function assertDontSee(string $text): static
    {
        Assert::assertStringNotContainsString(
            $text,
            $this->getContent(),
            "Response unexpectedly contains [{$text}]."
        );
        return $this;
    }

    public function assertSeeText(string $text): static
    {
        return $this->assertSee(strip_tags($text));
    }

    // ── JSON ────────────────────────────────────────────────────────────────

    public function assertJson(array $subset): static
    {
        $actual = $this->json();
        foreach ($subset as $key => $value) {
            Assert::assertArrayHasKey($key, $actual, "JSON response missing key [{$key}].");
            if ($value !== null) {
                Assert::assertEquals($value, $actual[$key], "JSON key [{$key}] mismatch.");
            }
        }
        return $this;
    }

    public function assertJsonPath(string $path, mixed $expected): static
    {
        $actual = $this->jsonPath($path);
        Assert::assertEquals($expected, $actual, "JSON path [{$path}] mismatch.");
        return $this;
    }

    public function assertJsonCount(int $count, string $key = null): static
    {
        $data = $key ? ($this->json()[$key] ?? []) : $this->json();
        Assert::assertCount($count, $data, "Expected JSON count {$count} at [{$key}].");
        return $this;
    }

    public function assertJsonContains(array $keys): static
    {
        $json = $this->json();
        foreach ($keys as $key) {
            Assert::assertArrayHasKey($key, $json, "JSON response missing key [{$key}].");
        }
        return $this;
    }

    public function assertJsonMissing(array $keys): static
    {
        $json = $this->json();
        foreach ($keys as $key) {
            Assert::assertArrayNotHasKey($key, $json, "JSON response unexpectedly contains key [{$key}].");
        }
        return $this;
    }

    // ── Headers ───────────────────────────────────────────────────────────

    public function assertHeader(string $name, string $value = null): static
    {
        $headers = array_change_key_case($this->getHeaders());
        $key     = strtolower($name);
        Assert::assertArrayHasKey($key, $headers, "Response missing header [{$name}].");
        if ($value !== null) {
            Assert::assertEquals($value, $headers[$key], "Header [{$name}] mismatch.");
        }
        return $this;
    }

    public function assertHeaderMissing(string $name): static
    {
        $headers = array_change_key_case($this->getHeaders());
        Assert::assertArrayNotHasKey(strtolower($name), $headers);
        return $this;
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    public function json(string $key = null): mixed
    {
        if (!isset($this->decodedJson)) {
            $this->decodedJson = json_decode($this->getContent(), true) ?? [];
        }
        if ($key !== null) {
            return $this->decodedJson[$key] ?? null;
        }
        return $this->decodedJson;
    }

    protected function jsonPath(string $path): mixed
    {
        $data = $this->json();
        foreach (explode('.', $path) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) return null;
            $data = $data[$segment];
        }
        return $data;
    }

    public function dump(): static
    {
        echo "\n[TestResponse] Status: {$this->getStatusCode()}\n";
        echo substr($this->getContent(), 0, 2000) . "\n";
        return $this;
    }
}
