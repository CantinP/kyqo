<?php

use Kyqo\Core\Application;

if (!function_exists('app')) {
    function app(string $abstract = null, array $parameters = []): mixed
    {
        $instance = Application::getInstance();
        if (is_null($abstract)) {
            return $instance;
        }
        return $instance->make($abstract, $parameters);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (!function_exists('config')) {
    function config(string $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return app('config');
        }
        return app('config')->get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return app()->basePath($path);
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return app()->appPath($path);
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return app()->configPath($path);
    }
}

if (!function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return app()->databasePath($path);
    }
}

if (!function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return app()->resourcePath($path);
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return app()->storagePath($path);
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return app()->publicPath($path);
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): mixed
    {
        return app('view')->make($template, $data);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url = null, int $status = 302): mixed
    {
        if (is_null($url)) {
            return app('redirect');
        }
        return app('redirect')->to($url, $status);
    }
}

if (!function_exists('response')) {
    function response(mixed $content = '', int $status = 200, array $headers = []): mixed
    {
        if (func_num_args() === 0) {
            return app('response');
        }
        return app('response')->make($content, $status, $headers);
    }
}

if (!function_exists('request')) {
    function request(string $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return app('request');
        }
        return app('request')->get($key, $default);
    }
}

if (!function_exists('session')) {
    function session(string $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return app('session');
        }
        return app('session')->get($key, $default);
    }
}

if (!function_exists('auth')) {
    function auth(string $guard = null): mixed
    {
        return app('auth')->guard($guard);
    }
}

if (!function_exists('route')) {
    function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        return app('url')->route($name, $parameters, $absolute);
    }
}

if (!function_exists('abort')) {
    function abort(int $code, string $message = '', array $headers = []): never
    {
        app()->abort($code, $message, $headers);
    }
}

if (!function_exists('now')) {
    function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}

if (!function_exists('collect')) {
    function collect(array $items = []): \Kyqo\Core\Support\Collection
    {
        return new \Kyqo\Core\Support\Collection($items);
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$vars): never
    {
        foreach ($vars as $var) {
            echo '<pre>';
            var_dump($var);
            echo '</pre>';
        }
        exit(1);
    }
}

if (!function_exists('dump')) {
    function dump(mixed ...$vars): void
    {
        foreach ($vars as $var) {
            echo '<pre>';
            var_dump($var);
            echo '</pre>';
        }
    }
}
