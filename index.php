<?php

/**
 * Pebblestack front controller. All HTTP requests funnel through this file
 * via the .htaccess rewrite. Keep this small — real work happens in src/.
 */

declare(strict_types=1);

$root = __DIR__;

// Composer autoload — fail fast with a useful message if missing.
if (!is_file($root . '/vendor/autoload.php')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Setup needed</title>';
    echo '<style>body{font:15px system-ui;margin:3rem auto;max-width:560px;padding:0 1rem;color:#1c1917}';
    echo 'code{background:#f5f5f4;padding:.125rem .375rem;border-radius:3px;font-family:ui-monospace,monospace}</style>';
    echo '<h1>Pebblestack: dependencies not installed</h1>';
    echo '<p>The <code>vendor/</code> directory is missing. From the project root, run:</p>';
    echo '<pre><code>composer install --no-dev --optimize-autoloader</code></pre>';
    echo '<p>Then refresh this page.</p>';
    exit;
}
require $root . '/vendor/autoload.php';

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

$app = new \Pebblestack\Core\App($root);
$request = \Pebblestack\Core\Request::fromGlobals();
$response = $app->handle($request);
$response->send();
