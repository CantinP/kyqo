<?php

/**
 * Kyqo – Public entry point.
 *
 * All HTTP traffic is routed here by the web server (Apache / Nginx).
 * The document root should point to this directory.
 */

declare(strict_types=1);

// ── Maintenance mode ────────────────────────────────────────────────────────
if (file_exists(__DIR__ . '/../storage/framework/down')) {
    http_response_code(503);
    header('Retry-After: 60');
    if (file_exists(__DIR__ . '/../resources/views/errors/503.php')) {
        include __DIR__ . '/../resources/views/errors/503.php';
    } else {
        echo '<h1>503 – Be right back.</h1>';
    }
    exit;
}

// ── Autoloader ──────────────────────────────────────────────────────────────
require __DIR__ . '/../vendor/autoload.php';

// ── Bootstrap ───────────────────────────────────────────────────────────────
$app = require_once __DIR__ . '/../bootstrap/app.php';

// ── HTTP Kernel ─────────────────────────────────────────────────────────────
$kernel = $app->make(\Kyqo\Http\Kernel::class);

$request  = \Kyqo\Http\Request::capture();
$response = $kernel->handle($request);
$response->send();
