<?php

declare(strict_types=1);

final class MigrationRunner
{
    public function __construct(
        private readonly Database $database,
        private readonly string $migrationsDirectory,
        private readonly ?string $lockDirectory = null,
    ) {
    }

    /**
     * Cheap currency check: compare lexically greatest migration filename
     * with the greatest recorded migration key. Skip full scan when equal.
     */
    public function isCurrent(): bool
    {
        $latestFile = $this->latestMigrationFilename();
        if ($latestFile === null) {
            return true;
        }

        if (!$this->hasMigrationsTable()) {
            return false;
        }

        $latestApplied = $this->database->fetchOne(
            'SELECT migration_key FROM schema_migrations ORDER BY migration_key DESC LIMIT 1'
        );

        if ($latestApplied === null) {
            return false;
        }

        return ($latestApplied['migration_key'] ?? null) === $latestFile;
    }

    /**
     * Run pending migrations. Uses a project/platform-specific lock when a lock directory is provided.
     *
     * @return list<string> Applied migration keys
     */
    public function runPending(): array
    {
        if ($this->isCurrent()) {
            return [];
        }

        $lockHandle = $this->acquireLock();

        try {
            // Re-check after acquiring the lock (concurrent requests).
            if ($this->isCurrent()) {
                return [];
            }

            $this->ensureMigrationsTable();

            $applied = [];
            foreach ($this->pendingMigrations() as $filename => $path) {
                $this->applyMigration($filename, $path);
                $applied[] = $filename;
            }

            return $applied;
        } finally {
            $this->releaseLock($lockHandle);
        }
    }

    /**
     * @return array<string, string> filename => absolute path
     */
    public function pendingMigrations(): array
    {
        $this->ensureMigrationsTable();

        $appliedRows = $this->database->fetchAll('SELECT migration_key FROM schema_migrations');
        $applied = [];
        foreach ($appliedRows as $row) {
            $applied[$row['migration_key']] = true;
        }

        $pending = [];
        foreach ($this->migrationFiles() as $filename => $path) {
            if (!isset($applied[$filename])) {
                $pending[$filename] = $path;
            }
        }

        return $pending;
    }

    private function applyMigration(string $filename, string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Unable to read migration: ' . $filename);
        }

        $sql = trim($sql);
        if ($sql === '') {
            throw new RuntimeException('Migration is empty: ' . $filename);
        }

        $pdo = $this->database->pdo();
        $pdo->beginTransaction();

        try {
            $pdo->exec($sql);
            $statement = $pdo->prepare(
                'INSERT INTO schema_migrations (migration_key, applied_at) VALUES (:key, :applied_at)'
            );
            $statement->execute([
                'key' => $filename,
                'applied_at' => gmdate('c'),
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Migration failed: ' . $filename . ' — ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function ensureMigrationsTable(): void
    {
        if ($this->hasMigrationsTable()) {
            return;
        }

        $this->database->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                migration_key TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL
            )'
        );
    }

    private function hasMigrationsTable(): bool
    {
        $row = $this->database->fetchOne(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'schema_migrations'"
        );

        return $row !== null;
    }

    /**
     * @return array<string, string>
     */
    private function migrationFiles(): array
    {
        if (!is_dir($this->migrationsDirectory)) {
            return [];
        }

        $files = glob($this->migrationsDirectory . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        $map = [];
        foreach ($files as $path) {
            $map[basename($path)] = $path;
        }

        return $map;
    }

    private function latestMigrationFilename(): ?string
    {
        $files = $this->migrationFiles();
        if ($files === []) {
            return null;
        }

        $keys = array_keys($files);

        return $keys[array_key_last($keys)];
    }

    /**
     * @return resource|null
     */
    private function acquireLock()
    {
        $directory = $this->lockDirectory;
        if ($directory === null) {
            return null;
        }

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create migration lock directory.');
        }

        $lockPath = rtrim($directory, '/') . '/migrations.lock';
        $handle = fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open migration lock file.');
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new RuntimeException('Unable to acquire migration lock.');
        }

        return $handle;
    }

    /**
     * @param resource|null $handle
     */
    private function releaseLock(mixed $handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
