<?php

declare(strict_types=1);

/**
 * Shared application bootstrap for SaaS Lab platform requests.
 */

$root = dirname(__DIR__);

require_once $root . '/core/Config.php';
require_once $root . '/core/Database.php';
require_once $root . '/core/MigrationRunner.php';
require_once $root . '/core/Session.php';
require_once $root . '/core/View.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/helpers.php';

Config::load($root);

$timezone = (string) Config::get('app_timezone', 'UTC');
if ($timezone !== '') {
    date_default_timezone_set($timezone);
}

$debug = (bool) Config::get('debug', false);
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

set_exception_handler(static function (Throwable $exception) use ($debug): void {
    lab_log('error', $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
    ]);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    if ($debug) {
        echo '<h1>Application error</h1>';
        echo '<pre>' . e($exception->getMessage()) . "\n\n" . e($exception->getTraceAsString()) . '</pre>';
        return;
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Something went wrong</title>';
    echo '<link rel="stylesheet" href="' . e(url_path('/assets/app.css')) . '"></head><body class="page-error">';
    echo '<main class="panel"><h1>Something went wrong</h1><p>Please try again later.</p></main></body></html>';
});

set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($debug): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

Session::start();

// Automatic CSRF verification for state-changing requests (platform + projects).
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    verify_csrf_or_fail();
}

return $root;
