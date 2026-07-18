<?php

declare(strict_types=1);

/**
 * Phase 2 verification (CLI).
 *
 * Usage: php scripts/verify_phase2.php
 */

$root = dirname(__DIR__);
$failed = 0;
$messages = [];

function assert_true(bool $condition, string $message): void
{
    global $failed, $messages;
    $messages[] = ($condition ? 'OK  ' : 'FAIL ') . $message;
    if (!$condition) {
        $failed++;
    }
}

// Backup any existing runtime DB/lock so local dev state is restored afterward.
$dataDir = $root . '/data';
$backupDir = $root . '/storage/uploads/_phase2_backup_' . bin2hex(random_bytes(4));
mkdir($backupDir, 0775, true);
foreach (['platform.sqlite', 'platform.sqlite-wal', 'platform.sqlite-shm', 'installed.lock', 'migrations.lock'] as $name) {
    $path = $dataDir . '/' . $name;
    if (is_file($path)) {
        rename($path, $backupDir . '/' . $name);
    }
}

require $root . '/core/Config.php';
require $root . '/core/Database.php';
require $root . '/core/MigrationRunner.php';
require $root . '/core/Session.php';
require $root . '/core/View.php';
require $root . '/core/Auth.php';
require $root . '/core/helpers.php';
require $root . '/core/Installer.php';

Config::reset();
Database::resetConnections();
Auth::reset();

// Ensure local config exists for installer checks.
if (!is_file($root . '/config.local.php')) {
    copy($root . '/config.local.example.php', $root . '/config.local.php');
    file_put_contents(
        $root . '/config.local.php',
        "<?php\nreturn [\n    'base_url' => 'http://127.0.0.1:8080',\n    'environment' => 'development',\n    'debug' => false,\n    'session' => ['secure' => false, 'path' => '/'],\n];\n"
    );
}

Config::load($root);

// Start session before any assertions/output.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
Session::start();

$installer = new Installer($root);
$installResult = $installer->install('Admin User', 'admin@example.com', 'password123', 'password123');
assert_true($installResult['ok'] === true, 'Install succeeds for auth verification');
assert_true(is_file($dataDir . '/installed.lock'), 'installed.lock present');

Database::resetConnections();
Auth::reset();
$auth = Auth::instance();

$memberId = $auth->register('Member One', 'member@example.com', 'password123');
assert_true($memberId > 0, 'Valid registration works');
assert_true($auth->check() === true, 'Registration logs the user in');
$visitToken = $auth->visitToken();
assert_true(is_string($visitToken) && strlen($visitToken) === 32, 'Visit token created on authentication');

$duplicateFailed = false;
try {
    $auth->register('Member Two', 'member@example.com', 'password123');
} catch (InvalidArgumentException $exception) {
    $duplicateFailed = str_contains($exception->getMessage(), 'Unable to create that account');
}
assert_true($duplicateFailed, 'Duplicate registration fails with generic message');

$auth->logout();
Auth::reset();
Session::start();
$auth = Auth::instance();
assert_true($auth->check() === false, 'Logout ends authenticated access');
assert_true(empty($_SESSION['lab_visit_token']), 'Visit token cleared on logout');
assert_true(empty($_SESSION['lab_opened_projects']), 'Opened projects cleared on logout');

assert_true($auth->login('member@example.com', 'password123') === true, 'Valid login works');
assert_true($auth->login('member@example.com', 'wrong-password') === false, 'Invalid password fails while session remains for current user');

// Fresh login for suspended check
$auth->logout();
Auth::reset();
Session::start();
$auth = Auth::instance();

platform_db()->run('UPDATE users SET status = :status WHERE email = :email', [
    'status' => 'suspended',
    'email' => 'member@example.com',
]);
assert_true($auth->login('member@example.com', 'password123') === false, 'Suspended user cannot log in');

platform_db()->run('UPDATE users SET status = :status WHERE email = :email', [
    'status' => 'active',
    'email' => 'member@example.com',
]);

assert_true($auth->login('admin@example.com', 'password123') === true, 'Admin login works');
assert_true($auth->isAdmin() === true, 'Admin role detected');
assert_true($auth->canAccessProject(['access_mode' => 'archived']) === true, 'Admin can access archived projects');

$auth->logout();
Auth::reset();
Session::start();
$auth = Auth::instance();
assert_true($auth->login('member@example.com', 'password123') === true, 'Member login works after reactivation');
assert_true($auth->isAdmin() === false, 'Member is not admin');
assert_true($auth->canAccessProject(['access_mode' => 'lab']) === true, 'Member can access lab projects');
assert_true($auth->canAccessProject(['access_mode' => 'archived']) === false, 'Member cannot access archived projects');
assert_true($auth->markProjectOpened('health-rival') === true, 'project_opened session mark set once');
assert_true($auth->markProjectOpened('health-rival') === false, 'project_opened session mark deduplicated');

$auth->updateDisplayName('Member Updated');
assert_true(($auth->user()['name'] ?? '') === 'Member Updated', 'Profile display name updates');

// Preserve visit token across regenerate: login already regenerated; ensure token stable within visit.
$tokenA = $auth->visitToken();
$tokenB = $auth->visitToken();
assert_true($tokenA === $tokenB && is_string($tokenA), 'Visit token stable within authenticated visit');

foreach ($messages as $message) {
    echo $message . "\n";
}

// Restore prior runtime files
Database::resetConnections();
Auth::reset();
foreach (['platform.sqlite', 'platform.sqlite-wal', 'platform.sqlite-shm', 'installed.lock', 'migrations.lock'] as $name) {
    $path = $dataDir . '/' . $name;
    if (is_file($path)) {
        unlink($path);
    }
}
foreach (glob($backupDir . '/*') ?: [] as $backupFile) {
    rename($backupFile, $dataDir . '/' . basename($backupFile));
}
@rmdir($backupDir);

if ($failed > 0) {
    echo "\nPhase 2 verification failed: {$failed} check(s)\n";
    exit(1);
}

echo "\nPhase 2 verification passed.\n";
exit(0);
