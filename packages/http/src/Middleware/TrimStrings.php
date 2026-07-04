<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Http\Request;

/**
 * Trim whitespace from all string input fields.
 */
class TrimStrings
{
    protected array $except = ['password', 'password_confirmation', 'current_password'];

    public function handle(Request $request, \Closure $next): mixed
    {
        $this->trimInput($request);
        return $next($request);
    }

    protected function trimInput(Request $request): void
    {
        foreach ($request->all() as $key => $value) {
            if (in_array($key, $this->except, true)) continue;
            if (is_string($value)) {
                $request->merge([$key => trim($value)]);
            }
        }
    }
}
