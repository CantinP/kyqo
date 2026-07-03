<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Http\Request;
use Kyqo\Http\Response;

class ValidateBodySize
{
    protected int $maxBytes;

    public function __construct(int $maxBytes = 10 * 1024 * 1024)
    {
        $this->maxBytes = $maxBytes;
    }

    public function handle(Request $request, \Closure $next): mixed
    {
        $contentLength = (int) ($request->header('content-length', 0));

        if ($contentLength > $this->maxBytes) {
            return Response::make(
                json_encode([
                    'message' => 'Request body too large.',
                    'max_bytes' => $this->maxBytes,
                ]),
                413,
                ['Content-Type' => 'application/json']
            );
        }

        return $next($request);
    }
}
