<?php

declare(strict_types=1);

/**
 * Resolve the application root.
 *
 * Standard layout: repository/public/index.php → parent directory.
 * cPanel split deploy: public_html/index.php + .saas-lab-root marker
 * pointing at the private application directory (outside the web root).
 */
$root = null;

$envRoot = getenv('SAAS_LAB_ROOT');
if (is_string($envRoot) && $envRoot !== '' && is_dir($envRoot)) {
    $root = $envRoot;
}

if ($root === null) {
    $marker = __DIR__ . '/.saas-lab-root';
    if (is_file($marker)) {
        $markerRoot = trim((string) file_get_contents($marker));
        if ($markerRoot !== '' && is_dir($markerRoot)) {
            $root = $markerRoot;
        }
    }
}

if ($root === null) {
    $root = dirname(__DIR__);
}

$root = rtrim($root, '/');

if (!is_file($root . '/core/bootstrap.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'SaaS Lab application root is not configured correctly.';
    exit;
}

$root = require $root . '/core/bootstrap.php';
require_once $root . '/core/Router.php';
require_once $root . '/core/Installer.php';

$router = new Router($root);
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

$router->dispatch($method, $uri);
