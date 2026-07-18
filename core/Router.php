<?php

declare(strict_types=1);

/**
 * Front-controller router for platform account and installer routes.
 * Project and founder routes expand in later phases.
 */
final class Router
{
    public function __construct(
        private readonly string $rootPath,
    ) {
    }

    public function dispatch(string $method, string $uriPath): void
    {
        $path = $this->normalizePath($uriPath);
        $method = strtoupper($method);

        if ($path === '/install' || $path === '/install.php') {
            require $this->rootPath . '/app/install/pages/install.php';
            return;
        }

        // Remaining routes require a completed installation.
        if (!is_installed()) {
            redirect('/install');
        }

        match ($path) {
            '/', '' => require $this->rootPath . '/app/shared/pages/home.php',
            '/register' => require $this->rootPath . '/app/account/pages/register.php',
            '/login' => require $this->rootPath . '/app/account/pages/login.php',
            '/logout' => require $this->rootPath . '/app/account/pages/logout.php',
            '/profile' => require $this->rootPath . '/app/account/pages/profile.php',
            default => $this->notFound(),
        };
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

        // Strip base_url path prefix for subdirectory installs.
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
