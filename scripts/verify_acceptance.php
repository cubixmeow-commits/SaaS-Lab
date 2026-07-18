<?php

declare(strict_types=1);

/**
 * Local V1 acceptance verification covering Phases 3–7 flows.
 *
 * Usage: php scripts/verify_acceptance.php
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

$dataDir = $root . '/data';
$backupDir = $root . '/storage/uploads/_accept_backup_' . bin2hex(random_bytes(4));
mkdir($backupDir, 0775, true);
foreach (['platform.sqlite', 'platform.sqlite-wal', 'platform.sqlite-shm', 'installed.lock', 'migrations.lock'] as $name) {
    $path = $dataDir . '/' . $name;
    if (is_file($path)) {
        rename($path, $backupDir . '/' . $name);
    }
}

// Clear generated projects from prior runs
foreach (['health-rival', 'ats-resume-kit'] as $slug) {
    $dir = $root . '/projects/' . $slug;
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}

require $root . '/core/Config.php';
require $root . '/core/Database.php';
require $root . '/core/MigrationRunner.php';
require $root . '/core/Session.php';
require $root . '/core/View.php';
require $root . '/core/Auth.php';
require $root . '/core/Csrf.php';
require $root . '/core/Project.php';
require $root . '/core/ProjectContext.php';
require $root . '/core/ProjectFactory.php';
require $root . '/core/EventLogger.php';
require $root . '/core/FounderMetrics.php';
require $root . '/core/helpers.php';
require $root . '/core/Installer.php';

Config::reset();
Database::resetConnections();
Auth::reset();
ProjectContext::clear();

if (!is_file($root . '/config.local.php')) {
    file_put_contents(
        $root . '/config.local.php',
        "<?php\nreturn [\n    'base_url' => 'http://127.0.0.1:8080',\n    'environment' => 'development',\n    'debug' => false,\n    'session' => ['secure' => false, 'path' => '/'],\n];\n"
    );
}

Config::load($root);
Session::start();

$installer = new Installer($root);
$install = $installer->install('Admin User', 'admin@example.com', 'password123', 'password123');
assert_true($install['ok'], 'Installer completes');

Database::resetConnections();
Auth::reset();
Session::start();
$auth = Auth::instance();

$memberId = $auth->register('Member One', 'member@example.com', 'password123');
assert_true($memberId > 0, 'Member registration works');

$auth->logout();
Auth::reset();
Session::start();
$auth = Auth::instance();
assert_true($auth->login('admin@example.com', 'password123'), 'Admin login works');

$factory = new ProjectFactory($root);
$created = $factory->create(
    'Health Rival',
    'health-rival',
    'Competitive health challenges using wearable data.',
    'item_created',
    'lab',
    'logged-in-prototype'
);
assert_true($created['ok'], 'Health Rival project created');
assert_true(is_dir($root . '/projects/health-rival'), 'Health Rival folder exists');
assert_true(is_file($root . '/projects/health-rival/data/project.sqlite'), 'Health Rival SQLite exists');
assert_true(is_file($root . '/projects/health-rival/project.json'), 'Health Rival project.json exists');

$dup = $factory->create('Health Rival', 'health-rival', 'x', 'item_created');
assert_true(!$dup['ok'], 'Duplicate slug rejected');

$bad = $factory->create('Bad', 'Login', 'x', 'item_created');
assert_true(!$bad['ok'], 'Invalid/reserved slug rejected');

// Member opens project and creates item
$auth->logout();
Auth::reset();
Session::start();
$auth = Auth::instance();
assert_true($auth->login('member@example.com', 'password123'), 'Member login for project use');

$project = Project::findBySlug('health-rival');
assert_true($project !== null, 'Project loads by slug');
assert_true($auth->canAccessProject($project), 'Member can access lab project');
assert_true($project->coreAction() === 'item_created', 'Core action from project.json is item_created');

ProjectContext::set($project);
$project->ensureMigrations();
EventLogger::maybeProjectOpened();
EventLogger::maybeProjectOpened(); // dedupe

$events = platform_db()->fetchAll(
    'SELECT event_name FROM project_events WHERE project_id = :id ORDER BY id ASC',
    ['id' => $project->id()]
);
$opened = array_values(array_filter($events, static fn ($e) => $e['event_name'] === 'project_opened'));
assert_true(count($opened) === 1, 'project_opened emitted once per visit');

$now = utc_now();
$result = project_db()->run(
    'INSERT INTO items (user_id, title, notes, status, created_at, updated_at)
     VALUES (:user_id, :title, :notes, :status, :created_at, :updated_at)',
    [
        'user_id' => $auth->id(),
        'title' => 'Morning run',
        'notes' => '5k',
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]
);
$itemId = (int) $result->lastInsertId();
lab_event($project->coreAction(), ['item_id' => $itemId]);

$coreEvents = platform_db()->fetchAll(
    'SELECT event_name, project_version, event_data FROM project_events
     WHERE project_id = :id AND event_name = :name',
    ['id' => $project->id(), 'name' => 'item_created']
);
assert_true(count($coreEvents) === 1, 'item_created core action recorded');
assert_true(($coreEvents[0]['project_version'] ?? '') === '0.1.0', 'Event stamped with project version');

// Data isolation for another user
$otherItems = project_db()->fetchAll(
    'SELECT * FROM items WHERE user_id = :user_id',
    ['user_id' => 999999]
);
assert_true($otherItems === [], 'User-scoped query returns no foreign items');

$memberItems = project_db()->fetchAll(
    'SELECT * FROM items WHERE user_id = :user_id',
    ['user_id' => $auth->id()]
);
assert_true(count($memberItems) === 1, 'Member sees own item');

// Second project
ProjectContext::clear();
$auth->logout();
Auth::reset();
Session::start();
$auth = Auth::instance();
assert_true($auth->login('admin@example.com', 'password123'), 'Admin login for second project');
$second = $factory->create(
    'ATS Resume Kit',
    'ats-resume-kit',
    'Resume kit experiment.',
    'item_created',
    'lab',
    'logged-in-prototype'
);
assert_true($second['ok'], 'ATS Resume Kit created');

$auth->logout();
Auth::reset();
Session::start();
$auth = Auth::instance();
assert_true($auth->login('member@example.com', 'password123'), 'Shared member opens second project');
$projectB = Project::findBySlug('ats-resume-kit');
ProjectContext::set($projectB);
$projectB->ensureMigrations();
EventLogger::maybeProjectOpened();

$itemsB = project_db()->fetchAll(
    'SELECT * FROM items WHERE user_id = :user_id',
    ['user_id' => $auth->id()]
);
assert_true($itemsB === [], 'Second project data is isolated from Health Rival');

ProjectContext::clear();
$rows = FounderMetrics::projectRows();
assert_true(count($rows) === 2, 'Founder dashboard lists both projects');

$health = null;
foreach ($rows as $row) {
    if ($row['project']['slug'] === 'health-rival') {
        $health = $row;
    }
}
assert_true($health !== null, 'Health Rival metrics row present');
assert_true((int) $health['users'] >= 1, 'Users metric counts authenticated event emitters');
assert_true((int) $health['core_actions_7d'] >= 1, 'Core actions 7d counts item_created');
assert_true($health['last_activity'] !== null, 'Last activity populated');

// Routing validation helpers
assert_true(Project::isValidSlug('health-rival'), 'Valid slug accepted');
assert_true(!Project::isValidSlug('../etc'), 'Traversal slug rejected');
assert_true(Project::isReservedSlug('founder'), 'Reserved slug detected');
assert_true(Project::isValidPageName('dashboard'), 'Valid page accepted');
assert_true(!Project::isValidPageName('dashboard.php'), 'Page with extension rejected');

// Oversized event payload still records
ProjectContext::set($project);
lab_event('custom_debug_event', ['blob' => str_repeat('x', 20000)]);
$oversized = platform_db()->fetchOne(
    "SELECT event_data FROM project_events WHERE event_name = 'custom_debug_event' ORDER BY id DESC LIMIT 1"
);
assert_true(
    is_string($oversized['event_data'] ?? null) && str_contains((string) $oversized['event_data'], 'payload_exceeded_16kb'),
    'Oversized event payload omitted but event kept'
);

foreach ($messages as $message) {
    echo $message . "\n";
}

// Cleanup generated projects and restore prior DB
Database::resetConnections();
Auth::reset();
ProjectContext::clear();
foreach (['health-rival', 'ats-resume-kit'] as $slug) {
    $dir = $root . '/projects/' . $slug;
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
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
    echo "\nAcceptance verification failed: {$failed} check(s)\n";
    exit(1);
}

echo "\nAcceptance verification passed.\n";
exit(0);
