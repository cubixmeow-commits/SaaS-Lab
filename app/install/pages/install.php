<?php

declare(strict_types=1);

require_once app_root() . '/core/Installer.php';

$installer = new Installer(app_root());
$alreadyInstalled = $installer->isComplete();
$checks = $installer->environmentChecks();
$errors = [];

if (!$alreadyInstalled && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    // CSRF already verified in bootstrap for POST requests.
    $result = $installer->install(
        (string) ($_POST['name'] ?? ''),
        (string) ($_POST['email'] ?? ''),
        (string) ($_POST['password'] ?? ''),
        (string) ($_POST['password_confirmation'] ?? '')
    );

    if ($result['ok']) {
        flash('success', 'SaaS Lab is installed. You can log in once authentication is available.');
        redirect('/install');
    }

    $errors = $result['errors'];
}

view('install/install', [
    'title' => 'Install SaaS Lab',
    'alreadyInstalled' => $alreadyInstalled,
    'checks' => $checks,
    'checksPassed' => $installer->allChecksPassed($checks),
    'errors' => $errors,
    'flashes' => flash_messages(),
]);
