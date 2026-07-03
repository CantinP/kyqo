<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Http\Request;
use Kyqo\Http\Response;

/**
 * Validate Request Body Size
 *
 * FIX SEC-6: We check BOTH Content-Length header AND the actual body length
 *            already read by Request::capture(). A spoofed or missing
 *            Content-Length header no longer bypasses the limit.
 */
class ValidateBodySize
{
    protected int $maxBytes;

    public function __construct(int $maxBytes = 10 * 1024 * 1024)
    {
        $this->maxBytes = $maxBytes;
    }

    public function handle(Request $request, \Closure $next): mixed
    {
        // Check declared Content-Length first (fast, before reading)
        $contentLength = (int) ($request->header('content-length', 0));
        if ($contentLength > $this->maxBytes) {
            return $this->tooLarge();
        }

        // FIX SEC-6: Also check the actual body already in memory
        $actualSize = strlen($request->rawBody());
        if ($actualSize > $this->maxBytes) {
            return $this->tooLarge();
        }

        return $next($request);
    }

    protected function tooLarge(): Response
    {
        return Response::make(
            json_encode([
                'message'   => 'Request body too large.',
                'max_bytes' => $this->maxBytes,
            ]),
            413,
            ['Content-Type' => 'application/json']
        );
    }
}
