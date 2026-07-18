<?php

declare(strict_types=1);

final class ProjectFactory
{
    public function __construct(
        private readonly string $rootPath,
    ) {
    }

    /**
     * @return array{ok: bool, project?: Project, url?: string, errors: list<string>}
     */
    public function create(
        string $name,
        string $slug,
        string $description,
        string $coreAction,
        string $accessMode = 'lab',
        string $templateKey = 'logged-in-prototype',
    ): array {
        $errors = [];

        if (!auth()->isAdmin()) {
            return ['ok' => false, 'errors' => ['Only administrators can create projects.']];
        }

        $name = trim($name);
        $slug = strtolower(trim($slug));
        $description = trim($description);
        $coreAction = trim($coreAction);
        $accessMode = trim($accessMode);
        $templateKey = trim($templateKey);

        if ($name === '') {
            $errors[] = 'Project name is required.';
        }
        if (!Project::isValidSlug($slug)) {
            $errors[] = 'Slug must be lowercase letters, numbers, and hyphens.';
        } elseif (Project::isReservedSlug($slug)) {
            $errors[] = 'That slug is reserved by the platform.';
        }
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/', $coreAction)) {
            $errors[] = 'Core action must be lowercase snake_case.';
        }
        if (!in_array($accessMode, ['lab', 'private', 'public', 'archived'], true)) {
            $errors[] = 'Invalid access mode.';
        }
        $approved = Config::get('approved_templates', []);
        if (!is_array($approved) || !in_array($templateKey, $approved, true)) {
            $errors[] = 'Template is not approved.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $finalDir = $this->rootPath . '/projects/' . $slug;
        if (is_dir($finalDir) || is_file($finalDir)) {
            return ['ok' => false, 'errors' => ['A project with that slug already exists on disk.']];
        }

        $existing = platform_db()->fetchOne(
            'SELECT id FROM projects WHERE slug = :slug LIMIT 1',
            ['slug' => $slug]
        );
        if ($existing !== null) {
            return ['ok' => false, 'errors' => ['A project with that slug already exists.']];
        }

        $templateDir = $this->rootPath . '/templates/' . $templateKey;
        if (!is_dir($templateDir)) {
            return ['ok' => false, 'errors' => ['Template files are missing.']];
        }

        $stagingParent = $this->rootPath . '/storage/uploads/_project_staging';
        if (!is_dir($stagingParent) && !mkdir($stagingParent, 0775, true) && !is_dir($stagingParent)) {
            return ['ok' => false, 'errors' => ['Unable to create staging directory.']];
        }

        $stagingDir = $stagingParent . '/' . $slug . '_' . bin2hex(random_bytes(4));
        $platformInserted = false;
        $projectId = null;

        try {
            $this->copyTemplate($templateDir, $stagingDir);

            $nowLocal = (new DateTimeImmutable('now', new DateTimeZone((string) Config::get('app_timezone', 'UTC'))))->format(DateTimeInterface::ATOM);
            $nowUtc = utc_now();
            $version = '0.1.0';

            $projectJson = [
                'schema_version' => 1,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'template' => $templateKey,
                'version' => $version,
                'core_action' => $coreAction,
                'created_at' => $nowLocal,
            ];

            $json = json_encode($projectJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false || file_put_contents($stagingDir . '/project.json', $json . PHP_EOL) === false) {
                throw new RuntimeException('Unable to write project.json.');
            }
            @unlink($stagingDir . '/project.template.json');

            $dataDir = $stagingDir . '/data';
            if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
                throw new RuntimeException('Unable to create project data directory.');
            }
            if (!is_file($dataDir . '/.gitkeep')) {
                file_put_contents($dataDir . '/.gitkeep', '');
            }

            $dbPath = $dataDir . '/project.sqlite';
            Database::resetConnections();
            $projectDb = Database::project($dbPath);
            $runner = new MigrationRunner($projectDb, $stagingDir . '/app/migrations', $dataDir);
            $runner->runPending();

            $insert = platform_db()->run(
                'INSERT INTO projects
                    (slug, name, description, access_mode, template_key, current_version, core_action_name, created_at, updated_at)
                 VALUES
                    (:slug, :name, :description, :access_mode, :template_key, :current_version, :core_action_name, :created_at, :updated_at)',
                [
                    'slug' => $slug,
                    'name' => $name,
                    'description' => $description,
                    'access_mode' => $accessMode,
                    'template_key' => $templateKey,
                    'current_version' => $version,
                    'core_action_name' => $coreAction,
                    'created_at' => $nowUtc,
                    'updated_at' => $nowUtc,
                ]
            );
            $platformInserted = true;
            $projectId = (int) $insert->lastInsertId();

            $projectsRoot = $this->rootPath . '/projects';
            if (!is_dir($projectsRoot) && !mkdir($projectsRoot, 0775, true) && !is_dir($projectsRoot)) {
                throw new RuntimeException('Unable to access projects directory.');
            }

            if (!@rename($stagingDir, $finalDir)) {
                // Cross-device fallback
                $this->copyTemplate($stagingDir, $finalDir);
                $this->removeDirectory($stagingDir);
            }

            Database::resetConnections();
            $project = Project::findBySlug($slug);
            if ($project === null) {
                throw new RuntimeException('Project created but could not be loaded.');
            }

            return [
                'ok' => true,
                'project' => $project,
                'url' => $project->url(),
                'errors' => [],
            ];
        } catch (Throwable $exception) {
            lab_log('error', 'Project creation failed.', [
                'slug' => $slug,
                'error' => $exception->getMessage(),
            ]);

            if ($platformInserted && $projectId !== null) {
                try {
                    platform_db()->run('DELETE FROM projects WHERE id = :id', ['id' => $projectId]);
                } catch (Throwable) {
                    // best effort rollback
                }
            }

            if (is_dir($stagingDir)) {
                $this->removeDirectory($stagingDir);
            }
            if (is_dir($finalDir)) {
                $this->removeDirectory($finalDir);
            }

            return [
                'ok' => false,
                'errors' => ['Project creation failed. Please try again.'],
            ];
        }
    }

    public function archive(string $slug): bool
    {
        if (!auth()->isAdmin()) {
            return false;
        }

        $project = Project::findBySlug($slug);
        if ($project === null) {
            return false;
        }

        platform_db()->run(
            'UPDATE projects SET access_mode = :access_mode, updated_at = :updated_at WHERE id = :id',
            [
                'access_mode' => 'archived',
                'updated_at' => utc_now(),
                'id' => $project->id(),
            ]
        );

        return true;
    }

    private function copyTemplate(string $source, string $destination): void
    {
        if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
            throw new RuntimeException('Unable to create destination directory.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $target = $destination . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
                    throw new RuntimeException('Unable to create template subdirectory.');
                }
                continue;
            }

            // Never copy sqlite runtime files from a template.
            if (preg_match('/\.(sqlite|sqlite3|db)(-wal|-shm|-journal)?$/', $item->getFilename())) {
                continue;
            }

            if (!copy($item->getPathname(), $target)) {
                throw new RuntimeException('Unable to copy template file.');
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($directory);
    }
}
