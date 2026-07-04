<?php

use Kyqo\Core\Application;

if (!function_exists('app')) {
    function app(string $abstract = null, array $parameters = []): mixed
    {
        $instance = Application::getInstance();
        if ($abstract === null) return $instance;
        return $instance->make($abstract, $parameters);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) return $default;
        return match (strtolower((string) $value)) {
            'true',  '(true)'  => true,
            'false', '(false)' => false,
            'null',  '(null)'  => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (!function_exists('config')) {
    function config(string $key = null, mixed $default = null): mixed
    {
        $cfg = app('config');
        if ($key === null) return $cfg;
        return $cfg->get($key, $default);
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): \Kyqo\Http\Response
    {
        $html = app('view')->make($template, $data);
        return new \Kyqo\Http\Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}

if (!function_exists('auth')) {
    function auth(?string $guard = null): mixed
    {
        $manager = app('auth');
        return $guard ? $manager->guard($guard) : $manager;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): \Kyqo\Http\Response
    {
        return \Kyqo\Http\Response::redirect($url, $status);
    }
}

if (!function_exists('response')) {
    function response(string $content = '', int $status = 200, array $headers = []): \Kyqo\Http\Response
    {
        return new \Kyqo\Http\Response($content, $status, $headers);
    }
}

if (!function_exists('request')) {
    function request(?string $key = null, mixed $default = null): mixed
    {
        $req = app('request');
        if ($key === null) return $req;
        return $req->input($key, $default);
    }
}

if (!function_exists('session')) {
    function session(?string $key = null, mixed $default = null): mixed
    {
        $store = app('session');
        if ($key === null) return $store;
        return $store->get($key, $default);
    }
}

if (!function_exists('cache')) {
    function cache(?string $key = null, mixed $default = null): mixed
    {
        $mgr = app('cache');
        if ($key === null) return $mgr;
        return $mgr->get($key, $default);
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return app()->storagePath($path);
    }
}

if (!function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return app()->resourcePath($path);
    }
}

if (!function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return app()->databasePath($path);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return app()->basePath($path);
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return app()->publicPath($path);
    }
}

if (!function_exists('collect')) {
    function collect(array $items = []): \Kyqo\Core\Support\Collection
    {
        return new \Kyqo\Core\Support\Collection($items);
    }
}

if (!function_exists('bcrypt')) {
    function bcrypt(string $value, array $options = []): string
    {
        return app('hash')->make($value, $options);
    }
}

if (!function_exists('now')) {
    function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}

if (!function_exists('abort')) {
    function abort(int $code, string $message = ''): never
    {
        throw new \Kyqo\Http\Exceptions\HttpException($code, $message);
    }
}

if (!function_exists('logger')) {
    function logger(string $message = null, array $context = []): mixed
    {
        $log = app('log');
        if ($message === null) return $log;
        $log->debug($message, $context);
        return null;
    }
}

if (!function_exists('url')) {
    function url(string $path = '', array $parameters = []): string
    {
        return app('url')->to($path, $parameters);
    }
}

if (!function_exists('route')) {
    function route(string $name, array $parameters = []): string
    {
        return app('url')->route($name, $parameters);
    }
}

if (!function_exists('route_url')) {
    function route_url(string $name, array $parameters = []): string
    {
        return route($name, $parameters);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = null): mixed
    {
        return app('session')->get('_old_input.' . $key, $default);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        $session = app('session');
        if (!$session->has('_token')) {
            $session->put('_token', bin2hex(random_bytes(32)));
        }
        return $session->get('_token');
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . htmlspecialchars(strtoupper($method), ENT_QUOTES, 'UTF-8') . '">';
    }
}

/**
 * Translate a key using the Translator service.
 * Falls back to the key itself when no translation exists.
 */
if (!function_exists('trans')) {
    function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        try {
            return app('translator')->get($key, $replace, $locale);
        } catch (\Throwable) {
            return $key;
        }
    }
}

if (!function_exists('__')) {
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        return trans($key, $replace, $locale);
    }
}

/** Short alias for trans(). */
if (!function_exists('lang')) {
    function lang(string $key, array $replace = [], ?string $locale = null): string
    {
        return trans($key, $replace, $locale);
    }
}

/**
 * Broadcast an event.
 *
 * Usage:
 *   broadcast(new OrderShipped($order));
 */
if (!function_exists('broadcast')) {
    function broadcast(\Kyqo\Broadcasting\ShouldBroadcast $event): void
    {
        app('broadcast')->event($event);
    }
}

if (!function_exists('value')) {
    function value(mixed $value, ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('tap')) {
    function tap(mixed $value, ?callable $callback = null): mixed
    {
        if ($callback === null) return $value;
        $callback($value);
        return $value;
    }
}

if (!function_exists('with')) {
    function with(mixed $value, ?callable $callback = null): mixed
    {
        return $callback === null ? $value : $callback($value);
    }
}

if (!function_exists('blank')) {
    function blank(mixed $value): bool
    {
        if (is_null($value)) return true;
        if (is_string($value)) return trim($value) === '';
        if (is_array($value)) return empty($value);
        return false;
    }
}

if (!function_exists('filled')) {
    function filled(mixed $value): bool { return !blank($value); }
}

if (!function_exists('throw_if')) {
    function throw_if(bool $condition, \Throwable|string $exception, string ...$args): void
    {
        if ($condition) {
            throw is_string($exception) ? new \RuntimeException($exception) : $exception;
        }
    }
}

if (!function_exists('throw_unless')) {
    function throw_unless(bool $condition, \Throwable|string $exception, string ...$args): void
    {
        throw_if(!$condition, $exception, ...$args);
    }
}

if (!function_exists('rescue')) {
    function rescue(callable $callback, mixed $rescue = null): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return value($rescue, $e);
        }
    }
}

if (!function_exists('event')) {
    function event(object $event): mixed
    {
        return app('events')->dispatch($event);
    }
}

if (!function_exists('notify')) {
    /**
     * Send a notification to a notifiable.
     *
     * Usage:
     *   notify($user, new InvoicePaid($invoice));
     */
    function notify(object $notifiable, \Kyqo\Notifications\Notification $notification): void
    {
        app(\Kyqo\Notifications\NotificationSender::class)->send($notifiable, $notification);
    }
}
