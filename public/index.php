<?php

declare(strict_types=1);

$root = require dirname(__DIR__) . '/core/bootstrap.php';
require_once $root . '/core/Router.php';
require_once $root . '/core/Installer.php';

$router = new Router($root);
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

$router->dispatch($method, $uri);
