<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Http\Request;

/**
 * Convert empty string inputs to null.
 */
class ConvertEmptyStringsToNull
{
    protected array $except = ['password', 'password_confirmation', '_token', '_method'];

    public function handle(Request $request, \Closure $next): mixed
    {
        foreach ($request->all() as $key => $value) {
            if (in_array($key, $this->except, true)) continue;
            if ($value === '') {
                $request->merge([$key => null]);
            }
        }
        return $next($request);
    }
}
