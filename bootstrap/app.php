<?php

declare(strict_types=1);

use Kyqo\Core\Application;

// ── Environment ─────────────────────────────────────────────────────────────
$dotenv = __DIR__ . '/../.env';
if (file_exists($dotenv)) {
    foreach (file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"\'`");
        if (!isset($_ENV[$key]) && !isset($_SERVER[$key])) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }
}

// ── Application ─────────────────────────────────────────────────────────────
$app = Application::getInstance();
$app->setBasePath(dirname(__DIR__));

// ── Register Service Providers ──────────────────────────────────────────────
$providers = require __DIR__ . '/../config/app.php';
foreach ($providers['providers'] ?? [] as $provider) {
    $app->register(new $provider($app));
}

// ── Aliases (Facades) ────────────────────────────────────────────────────────
foreach ($providers['aliases'] ?? [] as $alias => $facade) {
    if (!class_exists($alias, false)) {
        class_alias($facade, $alias);
    }
}

return $app;
