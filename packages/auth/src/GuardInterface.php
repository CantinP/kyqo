<?php

namespace Kyqo\Auth;

/**
 * Contract for all authentication guards.
 */
interface GuardInterface
{
    public function user(): mixed;
    public function check(): bool;
    public function id(): mixed;
    public function attempt(array $credentials): bool;
    public function login(mixed $user): void;
    public function logout(): void;
}
