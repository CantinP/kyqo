<?php

namespace Kyqo\Core\Exceptions;

use Throwable;
use Kyqo\Http\Response;

/**
 * Kyqo Exception Handler
 *
 * Catches, reports and renders all application exceptions.
 * In debug mode, displays rich error information.
 * In production mode, renders safe user-facing error pages.
 *
 * FIX AUDIT-3: report() now resolves the PSR-3-compatible Logger from the
 *              container (alias 'log') instead of calling error_log() directly.
 *              Falls back to error_log() if the container is not bootstrapped
 *              or the logger cannot be resolved (e.g. in early boot failures).
 */
class Handler
{
    protected bool $debug;

    protected array $dontReport = [];

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Report the exception (log it).
     *
     * FIX AUDIT-3: delegates to the Logger instance bound as 'log' in the
     * Application container, falling back to error_log() when unavailable.
     */
    public function report(Throwable $e): void
    {
        if (!$this->shouldReport($e)) {
            return;
        }

        $message = sprintf(
            '%s: %s in %s:%d',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        // Try the PSR-3-compatible Logger from the container first.
        try {
            $logger = \Kyqo\Core\Application::getInstance()->make('log');
            $logger->error($message, [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);
            return;
        } catch (\Throwable) {
            // Container not yet bootstrapped or logger misconfigured — fall through.
        }

        // Fallback for early-boot failures.
        error_log($message);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Throwable $e): Response
    {
        $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        $status = ($status >= 100 && $status <= 599) ? $status : 500;

        if ($this->debug) {
            return Response::make($this->renderDebug($e), $status);
        }

        return Response::make($this->renderProduction($status), $status);
    }

    protected function shouldReport(Throwable $e): bool
    {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) return false;
        }
        return true;
    }

    protected function renderDebug(Throwable $e): string
    {
        return sprintf(
            '<html><head><title>Kyqo — Error</title><style>body{font-family:monospace;padding:2rem;background:#0f0f0f;color:#f8f8f2} h1{color:#ff5555} pre{background:#1e1e2e;padding:1rem;border-radius:8px;overflow:auto}</style></head><body><h1>%s</h1><p><strong>%s</strong> in <em>%s</em> on line <strong>%d</strong></p><pre>%s</pre></body></html>',
            htmlspecialchars(get_class($e),       ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($e->getMessage(),    ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($e->getFile(),       ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $e->getLine(),
            htmlspecialchars($e->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }

    protected function renderProduction(int $status): string
    {
        $messages = [
            404 => 'Page Not Found',
            403 => 'Forbidden',
            500 => 'Internal Server Error',
        ];

        $message    = $messages[$status] ?? 'An error occurred';
        $safeStatus  = htmlspecialchars((string) $status,  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMessage = htmlspecialchars($message,           ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return "<html><head><title>{$safeStatus} — {$safeMessage}</title></head>"
             . "<body><h1>{$safeStatus}</h1><p>{$safeMessage}</p></body></html>";
    }
}
