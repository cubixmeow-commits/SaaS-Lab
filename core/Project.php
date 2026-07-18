<?php

declare(strict_types=1);

final class Project
{
    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $row,
        private array $config,
        private readonly string $directory,
    ) {
    }

    public static function findBySlug(string $slug): ?self
    {
        if (!self::isValidSlug($slug)) {
            return null;
        }

        $row = platform_db()->fetchOne(
            'SELECT * FROM projects WHERE slug = :slug LIMIT 1',
            ['slug' => $slug]
        );
        if ($row === null) {
            return null;
        }

        $directory = app_root() . '/projects/' . $slug;
        $config = self::loadAndValidateConfig($directory . '/project.json', $slug);
        $project = new self($row, $config, $directory);
        $project->syncCoreActionCache();

        return $project;
    }

    public static function isValidSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }

    public static function isValidPageName(string $page): bool
    {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $page) === 1;
    }

    public static function isReservedSlug(string $slug): bool
    {
        $reserved = Config::get('reserved_project_slugs', []);
        if (!is_array($reserved)) {
            return false;
        }

        $normalized = strtolower($slug);
        foreach ($reserved as $item) {
            if (strtolower((string) $item) === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function loadAndValidateConfig(string $path, ?string $expectedSlug = null): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Project configuration is missing.');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Unable to read project configuration.');
        }

        $config = json_decode($raw, true);
        if (!is_array($config)) {
            throw new RuntimeException('Project configuration is not valid JSON.');
        }

        foreach (['schema_version', 'name', 'slug', 'template', 'version', 'core_action'] as $key) {
            if (!array_key_exists($key, $config)) {
                throw new RuntimeException('Project configuration missing key: ' . $key);
            }
        }

        if ((int) $config['schema_version'] !== 1) {
            throw new RuntimeException('Unsupported project schema_version.');
        }

        $slug = (string) $config['slug'];
        if (!self::isValidSlug($slug)) {
            throw new RuntimeException('Project configuration has an invalid slug.');
        }
        if ($expectedSlug !== null && $slug !== $expectedSlug) {
            throw new RuntimeException('Project configuration slug does not match directory.');
        }

        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/', (string) $config['core_action'])) {
            throw new RuntimeException('Project configuration has an invalid core_action.');
        }

        $config['description'] = (string) ($config['description'] ?? '');
        $config['name'] = (string) $config['name'];
        $config['version'] = (string) $config['version'];
        $config['template'] = (string) $config['template'];
        $config['core_action'] = (string) $config['core_action'];

        return $config;
    }

    public function id(): int
    {
        return (int) $this->row['id'];
    }

    public function name(): string
    {
        return (string) ($this->config['name'] ?? $this->row['name']);
    }

    public function slug(): string
    {
        return (string) $this->row['slug'];
    }

    public function description(): string
    {
        return (string) ($this->config['description'] ?? $this->row['description'] ?? '');
    }

    public function version(): string
    {
        return (string) ($this->config['version'] ?? $this->row['current_version'] ?? '0.1.0');
    }

    public function coreAction(): string
    {
        return (string) ($this->config['core_action'] ?? $this->row['core_action_name'] ?? 'core_action_completed');
    }

    public function accessMode(): string
    {
        return (string) ($this->row['access_mode'] ?? 'lab');
    }

    public function templateKey(): string
    {
        return (string) ($this->row['template_key'] ?? $this->config['template'] ?? '');
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function databasePath(): string
    {
        return $this->directory . '/data/project.sqlite';
    }

    public function pagePath(string $page): string
    {
        return $this->directory . '/app/pages/' . $page . '.php';
    }

    public function migrationsDirectory(): string
    {
        return $this->directory . '/app/migrations';
    }

    /**
     * @return array<string, mixed>
     */
    public function row(): array
    {
        return $this->row;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    public function url(string $page = 'dashboard'): string
    {
        if ($page === '' || $page === 'dashboard') {
            return '/p/' . $this->slug();
        }

        return '/p/' . $this->slug() . '/' . $page;
    }

    public function syncCoreActionCache(): void
    {
        $authoritative = $this->coreAction();
        $cached = (string) ($this->row['core_action_name'] ?? '');
        $version = $this->version();
        $cachedVersion = (string) ($this->row['current_version'] ?? '');

        if ($authoritative === $cached && $version === $cachedVersion) {
            return;
        }

        platform_db()->run(
            'UPDATE projects
             SET core_action_name = :core_action_name,
                 current_version = :current_version,
                 name = :name,
                 description = :description,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'core_action_name' => $authoritative,
                'current_version' => $version,
                'name' => $this->name(),
                'description' => $this->description(),
                'updated_at' => utc_now(),
                'id' => $this->id(),
            ]
        );

        $this->row['core_action_name'] = $authoritative;
        $this->row['current_version'] = $version;
        $this->row['name'] = $this->name();
        $this->row['description'] = $this->description();
    }

    public function ensureMigrations(): void
    {
        $runner = new MigrationRunner(
            Database::project($this->databasePath()),
            $this->migrationsDirectory(),
            $this->directory . '/data'
        );
        $runner->runPending();
    }
}
