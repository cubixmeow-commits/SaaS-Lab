<?php

declare(strict_types=1);

/**
 * Minimal front-controller router.
 * Phase 1 wires home + installer; later phases expand routes.
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

        if ($path === '/' || $path === '') {
            if (!is_installed()) {
                redirect('/install');
            }
            // Placeholder until Phase 3 account routes exist.
            require $this->rootPath . '/app/shared/pages/home.php';
            return;
        }

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
