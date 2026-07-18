<?php

declare(strict_types=1);

/**
 * Alternate installer entry when the web root is the repository root.
 * Preferred production entry: /install via public/index.php.
 */

$root = require __DIR__ . '/core/bootstrap.php';
require_once $root . '/core/Router.php';
require_once $root . '/core/Installer.php';

$router = new Router($root);
$router->dispatch((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), '/install');
