<?php

namespace Kyqo\Core\Translation;

use Kyqo\Http\Request;

/**
 * LocaleMiddleware — set the app locale from the request.
 *
 * Checks (in order):
 *   1. ?lang= query parameter
 *   2. Session key 'locale'
 *   3. Accept-Language header
 *   4. Config default
 *
 * Register in app/Http/Kernel.php:
 *   protected array $middleware = [
 *       \Kyqo\Core\Translation\LocaleMiddleware::class,
 *       ...
 *   ];
 */
class LocaleMiddleware
{
    public function handle(Request $request, \Closure $next): mixed
    {
        $translator = app('translator');
        $supported  = config('app.locales', ['en']);

        $locale = $this->resolveLocale($request, $supported);

        if ($locale) {
            $translator->setLocale($locale);
        }

        return $next($request);
    }

    private function resolveLocale(Request $request, array $supported): ?string
    {
        // 1. Query string
        $lang = $request->query('lang');
        if ($lang && in_array($lang, $supported, true)) return $lang;

        // 2. Session
        if (session_status() !== PHP_SESSION_NONE) {
            $sess = $_SESSION['locale'] ?? null;
            if ($sess && in_array($sess, $supported, true)) return $sess;
        }

        // 3. Accept-Language header
        $accept = $request->header('accept-language') ?? '';
        if ($accept) {
            $parts = explode(',', $accept);
            foreach ($parts as $part) {
                $tag = trim(explode(';', $part)[0]);
                $short = substr($tag, 0, 2);
                if (in_array($tag, $supported, true))   return $tag;
                if (in_array($short, $supported, true)) return $short;
            }
        }

        return null;
    }
}
