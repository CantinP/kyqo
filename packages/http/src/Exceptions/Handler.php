<?php

namespace Kyqo\Http\Exceptions;

use Kyqo\Http\Request;
use Kyqo\Http\Response;
use Kyqo\Http\Validation\ValidationException;

/**
 * Central Exception Handler.
 *
 * Override in app/Exceptions/Handler.php to customise error rendering.
 *
 * Usage in Kernel:
 *   $response = $this->exceptionHandler->render($request, $e);
 */
class Handler
{
    /** Exception classes that should not be reported (logged). */
    protected array $dontReport = [
        ValidationException::class,
        HttpException::class,
    ];

    public function __construct(protected bool $debug = false) {}

    public function report(\Throwable $e): void
    {
        foreach ($this->dontReport as $class) {
            if ($e instanceof $class) return;
        }
        error_log('[Kyqo] ' . get_class($e) . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine());
    }

    public function render(Request $request, \Throwable $e): Response
    {
        $this->report($e);

        // Validation errors — redirect back with errors
        if ($e instanceof ValidationException) {
            if ($request->wantsJson()) {
                return Response::make(
                    json_encode(['message' => 'The given data was invalid.', 'errors' => $e->errors()]),
                    422,
                    ['Content-Type' => 'application/json']
                );
            }
            // Store errors in session and redirect back
            $session = $this->trySession();
            if ($session) {
                $session->put('errors', $e->errors());
                $session->put('_old_input', $request->all());
            }
            return Response::redirect($request->header('Referer') ?? '/', 302);
        }

        $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
        $status = ($status >= 100 && $status <= 599) ? $status : 500;

        if ($request->wantsJson()) {
            $body = ['message' => $this->debug ? $e->getMessage() : $this->statusMessage($status)];
            if ($this->debug) {
                $body['exception'] = get_class($e);
                $body['file']      = $e->getFile();
                $body['line']      = $e->getLine();
                $body['trace']     = array_slice(explode("\n", $e->getTraceAsString()), 0, 15);
            }
            return Response::make(
                json_encode($body),
                $status,
                ['Content-Type' => 'application/json']
            );
        }

        return Response::make($this->renderHtml($status, $e), $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    protected function renderHtml(int $status, \Throwable $e): string
    {
        $title = $status . ' — ' . $this->statusMessage($status);

        if ($this->debug) {
            $msg   = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $trace = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $class = get_class($e);
            return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>{$title}</title>
<style>body{font-family:monospace;background:#0f0f0f;color:#e5e7eb;padding:2rem}
h1{color:#f87171}pre{background:#1f1f1f;padding:1rem;border-radius:6px;overflow:auto;font-size:.85rem}</style>
</head><body>
<h1>{$title}</h1>
<p><strong>{$class}</strong>: {$msg}</p>
<pre>{$trace}</pre>
</body></html>
HTML;
        }

        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>{$title}</title>
<style>body{font-family:-apple-system,sans-serif;background:#0f0f0f;color:#e5e7eb;
display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{text-align:center}.code{font-size:5rem;font-weight:800;color:#6366f1}
.msg{color:#9ca3af}</style></head><body>
<div class="box"><div class="code">{$status}</div>
<p class="msg">{$this->statusMessage($status)}</p></div>
</body></html>
HTML;
    }

    protected function statusMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',       401 => 'Unauthorized',
            403 => 'Forbidden',         404 => 'Not Found',
            405 => 'Method Not Allowed',408 => 'Request Timeout',
            409 => 'Conflict',          410 => 'Gone',
            422 => 'Unprocessable Entity',429 => 'Too Many Requests',
            500 => 'Internal Server Error', 502 => 'Bad Gateway',
            503 => 'Service Unavailable',   504 => 'Gateway Timeout',
            default => 'Error',
        };
    }

    protected function trySession(): mixed
    {
        try {
            return \Kyqo\Core\Application::getInstance()->make('session');
        } catch (\Throwable) {
            return null;
        }
    }
}
