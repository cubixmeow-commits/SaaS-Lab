<?php

declare(strict_types=1);

final class View
{
    public static function render(string $rootPath, string $name, array $data = [], ?string $layout = 'main'): void
    {
        $viewFile = self::resolveViewPath($rootPath, $name);
        if ($viewFile === null) {
            throw new RuntimeException('View not found: ' . $name);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = $rootPath . '/app/shared/views/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            echo $content;
            return;
        }

        require $layoutFile;
    }

    private static function resolveViewPath(string $rootPath, string $name): ?string
    {
        $candidates = [
            $rootPath . '/app/' . $name . '.php',
            $rootPath . '/app/shared/views/' . $name . '.php',
        ];

        // Support names like "account/login" or "install/checks"
        if (str_contains($name, '/')) {
            $candidates[] = $rootPath . '/app/' . $name . '.php';
        }

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        // Convention: area/views/name
        // e.g. founder/dashboard -> app/founder/views/dashboard.php
        $parts = explode('/', $name);
        if (count($parts) >= 2) {
            $area = array_shift($parts);
            $view = implode('/', $parts);
            $path = $rootPath . '/app/' . $area . '/views/' . $view . '.php';
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
