<?php

declare(strict_types=1);

final class Router
{
    public function __construct(
        private readonly string $rootPath,
    ) {
    }

    public function dispatch(string $method, string $uriPath): void
    {
        $path = $this->normalizePath($uriPath);

        if ($path === '/install' || $path === '/install.php') {
            require $this->rootPath . '/app/install/pages/install.php';
            return;
        }

        if (!is_installed()) {
            redirect('/install');
        }

        if (str_starts_with($path, '/p/')) {
            $this->dispatchProject($path);
            return;
        }

        if (str_starts_with($path, '/founder')) {
            auth()->requireAdmin();
        }

        match ($path) {
            '/', '' => require $this->rootPath . '/app/shared/pages/home.php',
            '/register' => require $this->rootPath . '/app/account/pages/register.php',
            '/login' => require $this->rootPath . '/app/account/pages/login.php',
            '/logout' => require $this->rootPath . '/app/account/pages/logout.php',
            '/profile' => require $this->rootPath . '/app/account/pages/profile.php',
            '/projects' => require $this->rootPath . '/app/account/pages/projects.php',
            '/founder' => require $this->rootPath . '/app/founder/pages/dashboard.php',
            '/founder/projects/new' => require $this->rootPath . '/app/founder/pages/project_new.php',
            '/founder/users' => require $this->rootPath . '/app/founder/pages/users.php',
            default => $this->dispatchFounderProject($path),
        };
    }

    private function dispatchFounderProject(string $path): void
    {
        if (preg_match('#^/founder/projects/([a-z0-9]+(?:-[a-z0-9]+)*)$#', $path, $matches) === 1) {
            $GLOBALS['founder_project_slug'] = $matches[1];
            require $this->rootPath . '/app/founder/pages/project_show.php';
            return;
        }

        if (preg_match('#^/founder/projects/([a-z0-9]+(?:-[a-z0-9]+)*)/archive$#', $path, $matches) === 1) {
            $GLOBALS['founder_project_slug'] = $matches[1];
            require $this->rootPath . '/app/founder/pages/project_archive.php';
            return;
        }

        $this->notFound();
    }

    private function dispatchProject(string $path): void
    {
        // Block traversal / encoded dots / null bytes before parsing.
        if (str_contains($path, '..') || str_contains($path, "\0") || str_contains($path, '\\')) {
            $this->notFound();
            return;
        }

        $remainder = substr($path, 3); // after /p/
        if ($remainder === false || $remainder === '') {
            $this->notFound();
            return;
        }

        $parts = explode('/', $remainder);
        $slug = $parts[0] ?? '';
        $page = $parts[1] ?? 'dashboard';

        if (count($parts) > 2) {
            $this->notFound();
            return;
        }

        if (!Project::isValidSlug($slug) || !Project::isValidPageName($page)) {
            $this->notFound();
            return;
        }

        if (str_contains($slug, '/') || str_contains($page, '/') || str_contains($page, '.')) {
            $this->notFound();
            return;
        }

        $project = Project::findBySlug($slug);
        if ($project === null) {
            $this->notFound();
            return;
        }

        auth()->requireLogin();
        if (!auth()->canAccessProject($project)) {
            http_response_code(403);
            view('shared/errors/403', ['title' => 'Forbidden']);
            return;
        }

        $pageFile = $project->pagePath($page);
        $pagesDir = realpath($project->directory() . '/app/pages');
        $realPage = realpath($pageFile);

        if ($pagesDir === false || $realPage === false || !str_starts_with($realPage, $pagesDir . DIRECTORY_SEPARATOR)) {
            $this->notFound();
            return;
        }

        if (!str_ends_with($realPage, '.php')) {
            $this->notFound();
            return;
        }

        ProjectContext::set($project);
        $project->ensureMigrations();
        EventLogger::maybeProjectOpened();

        require $realPage;
    }

    private function notFound(): void
    {
        http_response_code(404);
        view('shared/errors/404', [
            'title' => 'Not found',
        ]);
    }

    private function normalizePath(string $uriPath): string
    {
        $path = parse_url($uriPath, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $path = rawurldecode($path);
        if (str_contains($path, "\0") || str_contains($path, '..')) {
            return '/__invalid__';
        }

        $baseUrl = (string) Config::get('base_url', '');
        if ($baseUrl !== '') {
            $basePath = parse_url($baseUrl, PHP_URL_PATH);
            if (is_string($basePath) && $basePath !== '' && $basePath !== '/') {
                $prefix = rtrim($basePath, '/');
                if (str_starts_with($path, $prefix . '/') || $path === $prefix) {
                    $path = substr($path, strlen($prefix)) ?: '/';
                }
            }
        }

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path;
    }
}
