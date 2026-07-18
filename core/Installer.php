<?php

declare(strict_types=1);

final class Installer
{
    public function __construct(
        private readonly string $rootPath,
    ) {
    }

    public function isComplete(): bool
    {
        return is_file($this->lockPath());
    }

    /**
     * @return list<array{key: string, label: string, ok: bool, detail: string}>
     */
    public function environmentChecks(): array
    {
        $checks = [];

        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
        $checks[] = [
            'key' => 'php_version',
            'label' => 'PHP 8.2 or newer',
            'ok' => $phpOk,
            'detail' => 'Detected ' . PHP_VERSION . ($phpOk ? '' : ' — upgrade PHP to 8.2+ in Hostinger hPanel.'),
        ];

        $pdoSqlite = extension_loaded('pdo_sqlite');
        $checks[] = [
            'key' => 'pdo_sqlite',
            'label' => 'PDO SQLite extension',
            'ok' => $pdoSqlite,
            'detail' => $pdoSqlite
                ? 'pdo_sqlite is available.'
                : 'Enable the PDO SQLite extension for PHP in Hostinger.',
        ];

        foreach ([
            'data' => 'Platform database directory',
            'storage/logs' => 'Log directory',
            'storage/uploads' => 'Upload directory',
            'projects' => 'Projects directory',
        ] as $relative => $label) {
            $path = $this->rootPath . '/' . $relative;
            $writable = $this->ensureWritableDirectory($path);
            $checks[] = [
                'key' => 'writable_' . str_replace('/', '_', $relative),
                'label' => $label . ' writable',
                'ok' => $writable,
                'detail' => $writable
                    ? $relative . ' is writable.'
                    : 'Make ' . $relative . ' writable (chmod 775) and owned by the web user. On Hostinger, use File Manager → Permissions.',
            ];
        }

        $configLocal = is_file($this->rootPath . '/config.local.php');
        $checks[] = [
            'key' => 'config_local',
            'label' => 'Local configuration present',
            'ok' => $configLocal,
            'detail' => $configLocal
                ? 'config.local.php found.'
                : 'Copy config.local.example.php to config.local.php and set base_url before installing.',
        ];

        $baseUrl = (string) Config::get('base_url', '');
        $baseOk = $baseUrl !== '' && $baseUrl !== 'https://lab.example.com';
        $checks[] = [
            'key' => 'base_url',
            'label' => 'Production base URL configured',
            'ok' => $baseOk || (string) Config::get('environment') === 'development',
            'detail' => $baseOk || (string) Config::get('environment') === 'development'
                ? 'base_url is ' . ($baseUrl !== '' ? $baseUrl : '(empty)')
                : 'Update base_url in config.local.php away from the example placeholder.',
        ];

        return $checks;
    }

    public function allChecksPassed(array $checks): bool
    {
        foreach ($checks as $check) {
            if (!$check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{ok: bool, errors: list<string>}
     */
    public function install(string $name, string $email, string $password, string $passwordConfirmation): array
    {
        $errors = [];

        if ($this->isComplete()) {
            return ['ok' => false, 'errors' => ['Installation is already complete.']];
        }

        $checks = $this->environmentChecks();
        if (!$this->allChecksPassed($checks)) {
            return ['ok' => false, 'errors' => ['Environment checks must pass before installation.']];
        }

        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === '') {
            $errors[] = 'Administrator name is required.';
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'A valid administrator email is required.';
        }

        $minLength = (int) Config::get('password_min_length', 8);
        if (strlen($password) < $minLength) {
            $errors[] = 'Password must be at least ' . $minLength . ' characters.';
        }
        if (!hash_equals($password, $passwordConfirmation)) {
            $errors[] = 'Password confirmation does not match.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $dbPath = $this->rootPath . '/data/platform.sqlite';
        $createdDb = false;

        try {
            if (is_file($dbPath)) {
                // Allow retry after a failed prior attempt with an incomplete DB.
                @unlink($dbPath);
                foreach (['-wal', '-shm', '-journal'] as $suffix) {
                    $sidecar = $dbPath . $suffix;
                    if (is_file($sidecar)) {
                        @unlink($sidecar);
                    }
                }
            }

            Database::resetConnections();
            $database = Database::platform($this->rootPath);
            $createdDb = true;

            $runner = new MigrationRunner(
                $database,
                $this->rootPath . '/migrations/platform',
                $this->rootPath . '/data'
            );
            $runner->runPending();

            $now = utc_now();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($hash === false) {
                throw new RuntimeException('Unable to hash administrator password.');
            }

            $database->run(
                'INSERT INTO users (name, email, password_hash, role, status, created_at, updated_at)
                 VALUES (:name, :email, :password_hash, :role, :status, :created_at, :updated_at)',
                [
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => $hash,
                    'role' => 'admin',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $lockWritten = file_put_contents(
                $this->lockPath(),
                json_encode([
                    'installed_at' => $now,
                    'admin_email' => $email,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
            );

            if ($lockWritten === false) {
                throw new RuntimeException('Unable to write installed.lock.');
            }

            lab_log('info', 'SaaS Lab installation completed.', [
                'admin_email' => $email,
            ]);

            return ['ok' => true, 'errors' => []];
        } catch (Throwable $exception) {
            lab_log('error', 'Installation failed.', [
                'error' => $exception->getMessage(),
            ]);

            if ($createdDb && is_file($dbPath) && !is_file($this->lockPath())) {
                Database::resetConnections();
                @unlink($dbPath);
            }

            return [
                'ok' => false,
                'errors' => ['Installation failed. Check storage/logs/app.log for details.'],
            ];
        }
    }

    private function lockPath(): string
    {
        return $this->rootPath . '/data/installed.lock';
    }

    private function ensureWritableDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            if (!@mkdir($path, 0775, true) && !is_dir($path)) {
                return false;
            }
        }

        return is_writable($path);
    }
}
