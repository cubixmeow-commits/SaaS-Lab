<?php

declare(strict_types=1);

/**
 * Phase 1 verification (CLI). Does not claim Hostinger deployment success.
 *
 * Usage: php scripts/verify_phase1.php
 */

$root = dirname(__DIR__);
$failed = 0;

function assert_true(bool $condition, string $message) : void
{
    global $failed;
    if ($condition) {
        echo "OK  {$message}\n";
        return;
    }
    echo "FAIL {$message}\n";
    $failed++;
}

// Fresh temp workspace under storage for isolation
$tmp = $root . '/storage/uploads/_phase1_verify_' . bin2hex(random_bytes(4));
@mkdir($tmp . '/data', 0775, true);
@mkdir($tmp . '/storage/logs', 0775, true);
@mkdir($tmp . '/storage/uploads', 0775, true);
@mkdir($tmp . '/projects', 0775, true);
@mkdir($tmp . '/config', 0775, true);
@mkdir($tmp . '/migrations/platform', 0775, true);

foreach (glob($root . '/migrations/platform/*.sql') ?: [] as $file) {
    copy($file, $tmp . '/migrations/platform/' . basename($file));
}
copy($root . '/config/config.example.php', $tmp . '/config/config.example.php');

file_put_contents($tmp . '/config.local.php', "<?php\nreturn [\n    'base_url' => 'http://127.0.0.1:8080/saas-lab',\n    'environment' => 'development',\n    'debug' => true,\n    'session' => ['secure' => false, 'path' => null],\n];\n");

// Point helpers at temp root by chdir + symlink core
symlink($root . '/core', $tmp . '/core');
symlink($root . '/app', $tmp . '/app');

// Load against temp root
require $tmp . '/core/Config.php';
require $tmp . '/core/Database.php';
require $tmp . '/core/MigrationRunner.php';
require $tmp . '/core/Session.php';
require $tmp . '/core/View.php';
require $tmp . '/core/helpers.php';
require $tmp . '/core/Installer.php';

// Override app_root by defining a local bootstrap context:
// helpers.php hardcodes dirname(__DIR__) from core/, which still points at repo core via symlink.
// Config load uses explicit path instead for these checks.

Config::reset();
Database::resetConnections();
$config = Config::load($tmp);

assert_true(($config['environment'] ?? '') === 'development', 'Config loads local overrides');
assert_true(Config::sessionCookiePath() === '/saas-lab/', 'Session cookie path derived from base_url subdirectory');

$installer = new Installer($tmp);
$checks = $installer->environmentChecks();
assert_true($installer->allChecksPassed($checks), 'Installer environment checks pass in temp workspace');

$result = $installer->install('Admin User', 'admin@example.com', 'password123', 'password123');
assert_true($result['ok'] === true, 'Installer creates admin and completes');
assert_true(is_file($tmp . '/data/installed.lock'), 'installed.lock created only after success');
assert_true(is_file($tmp . '/data/platform.sqlite'), 'platform.sqlite created');

Database::resetConnections();
$db = Database::platform($tmp);
$tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
$tableNames = array_column($tables, 'name');
foreach (['schema_migrations', 'users', 'projects', 'project_events'] as $table) {
    assert_true(in_array($table, $tableNames, true), "Table exists: {$table}");
}

$migrations = $db->fetchAll('SELECT migration_key FROM schema_migrations ORDER BY migration_key');
assert_true(count($migrations) === 4, 'All four platform migrations recorded');

$admin = $db->fetchOne('SELECT * FROM users WHERE email = :email', ['email' => 'admin@example.com']);
assert_true($admin !== null && ($admin['role'] ?? '') === 'admin', 'Administrator row created with admin role');
assert_true(password_verify('password123', (string) ($admin['password_hash'] ?? '')), 'Administrator password is hashed and verifiable');

$columns = $db->fetchAll('PRAGMA table_info(projects)');
$columnNames = array_column($columns, 'name');
assert_true(!in_array('is_active', $columnNames, true), 'projects.is_active absent (access_mode is authoritative)');

$rerun = $installer->install('Other', 'other@example.com', 'password123', 'password123');
assert_true($rerun['ok'] === false, 'Installer refuses rerun when lock exists');

// Migration currency short-circuit
$runner = new MigrationRunner($db, $tmp . '/migrations/platform', $tmp . '/data');
assert_true($runner->isCurrent() === true, 'MigrationRunner reports current after install');
$applied = $runner->runPending();
assert_true($applied === [], 'MigrationRunner skips work when current');

// Cleanup
Database::resetConnections();
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $item) {
    if ($item->isLink() || $item->isFile()) {
        @unlink($item->getPathname());
    } else {
        @rmdir($item->getPathname());
    }
}
@rmdir($tmp);

if ($failed > 0) {
    echo "\nPhase 1 verification failed: {$failed} check(s)\n";
    exit(1);
}

echo "\nPhase 1 verification passed.\n";
exit(0);
