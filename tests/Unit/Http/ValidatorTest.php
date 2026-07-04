<?php

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Kyqo\Http\Validation\Validator;
use Kyqo\Http\Validation\ValidationException;

class ValidatorTest extends TestCase
{
    private function make(array $data, array $rules): Validator
    {
        return new Validator($data, $rules);
    }

    // --- required ---

    public function test_required_passes_when_present(): void
    {
        $v = $this->make(['name' => 'Alice'], ['name' => 'required']);
        $this->assertTrue($v->passes());
    }

    public function test_required_fails_when_missing(): void
    {
        $v = $this->make([], ['name' => 'required']);
        $this->assertFalse($v->passes());
        $this->assertArrayHasKey('name', $v->errors());
    }

    public function test_required_fails_when_empty_string(): void
    {
        $v = $this->make(['name' => ''], ['name' => 'required']);
        $this->assertFalse($v->passes());
    }

    // --- string ---

    public function test_string_passes_for_string_value(): void
    {
        $v = $this->make(['name' => 'hello'], ['name' => 'string']);
        $this->assertTrue($v->passes());
    }

    public function test_string_fails_for_integer(): void
    {
        $v = $this->make(['age' => 42], ['age' => 'string']);
        $this->assertFalse($v->passes());
    }

    // --- integer / numeric ---

    public function test_integer_passes_for_int(): void
    {
        $v = $this->make(['age' => 25], ['age' => 'integer']);
        $this->assertTrue($v->passes());
    }

    public function test_numeric_passes_for_float_string(): void
    {
        $v = $this->make(['price' => '9.99'], ['price' => 'numeric']);
        $this->assertTrue($v->passes());
    }

    // --- email ---

    public function test_email_passes_for_valid_email(): void
    {
        $v = $this->make(['email' => 'user@example.com'], ['email' => 'email']);
        $this->assertTrue($v->passes());
    }

    public function test_email_fails_for_invalid_email(): void
    {
        $v = $this->make(['email' => 'not-an-email'], ['email' => 'email']);
        $this->assertFalse($v->passes());
    }

    // --- min / max ---

    public function test_min_passes_when_string_long_enough(): void
    {
        $v = $this->make(['pass' => 'secret123'], ['pass' => 'min:6']);
        $this->assertTrue($v->passes());
    }

    public function test_min_fails_when_string_too_short(): void
    {
        $v = $this->make(['pass' => 'abc'], ['pass' => 'min:6']);
        $this->assertFalse($v->passes());
    }

    public function test_max_passes_when_under_limit(): void
    {
        $v = $this->make(['bio' => 'Short bio'], ['bio' => 'max:255']);
        $this->assertTrue($v->passes());
    }

    public function test_max_fails_when_over_limit(): void
    {
        $v = $this->make(['code' => '12345'], ['code' => 'max:4']);
        $this->assertFalse($v->passes());
    }

    // --- confirmed ---

    public function test_confirmed_passes_when_fields_match(): void
    {
        $v = $this->make(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password' => 'confirmed']
        );
        $this->assertTrue($v->passes());
    }

    public function test_confirmed_fails_when_fields_differ(): void
    {
        $v = $this->make(
            ['password' => 'secret', 'password_confirmation' => 'other'],
            ['password' => 'confirmed']
        );
        $this->assertFalse($v->passes());
    }

    // --- nullable ---

    public function test_nullable_skips_other_rules_for_null(): void
    {
        $v = $this->make(['bio' => null], ['bio' => 'nullable|string']);
        $this->assertTrue($v->passes());
    }

    // --- validate() throws on failure ---

    public function test_validate_throws_on_failure(): void
    {
        $this->expectException(ValidationException::class);
        $v = $this->make([], ['name' => 'required']);
        $v->validate();
    }

    public function test_validate_returns_validated_data_on_success(): void
    {
        $v    = $this->make(['name' => 'Bob', 'age' => 30], ['name' => 'required|string', 'age' => 'integer']);
        $data = $v->validate();
        $this->assertSame('Bob', $data['name']);
        $this->assertSame(30, $data['age']);
    }

    // --- multiple rules ---

    public function test_multiple_rules_all_checked(): void
    {
        $v = $this->make(['email' => ''], ['email' => 'required|email']);
        $this->assertFalse($v->passes());
        $errors = $v->errors();
        $this->assertNotEmpty($errors['email']);
    }
}
