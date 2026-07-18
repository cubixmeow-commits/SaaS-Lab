<?php

declare(strict_types=1);

final class Config
{
    private static ?array $config = null;

    public static function load(string $rootPath): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $examplePath = $rootPath . '/config/config.example.php';
        if (!is_file($examplePath)) {
            throw new RuntimeException('Missing config/config.example.php');
        }

        /** @var array $config */
        $config = require $examplePath;

        $localPath = $rootPath . '/config.local.php';
        if (is_file($localPath)) {
            /** @var array $local */
            $local = require $localPath;
            $config = self::merge($config, $local);
        }

        $config = self::applyEnvironmentOverrides($config);
        $config = self::normalize($config);

        self::$config = $config;

        return self::$config;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$config === null) {
            throw new RuntimeException('Configuration has not been loaded.');
        }

        $segments = explode('.', $key);
        $value = self::$config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function all(): array
    {
        if (self::$config === null) {
            throw new RuntimeException('Configuration has not been loaded.');
        }

        return self::$config;
    }

    public static function reset(): void
    {
        self::$config = null;
    }

    /**
     * Derive the session cookie path from base_url when session.path is null.
     */
    public static function sessionCookiePath(): string
    {
        $explicit = self::get('session.path');
        if (is_string($explicit) && $explicit !== '') {
            return self::normalizeCookiePath($explicit);
        }

        $baseUrl = (string) self::get('base_url', '/');
        $parts = parse_url($baseUrl);
        $path = $parts['path'] ?? '/';

        return self::normalizeCookiePath($path);
    }

    private static function normalizeCookiePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        $path = '/' . trim($path, '/');

        return $path . '/';
    }

    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private static function applyEnvironmentOverrides(array $config): array
    {
        $baseUrl = getenv('SAAS_LAB_BASE_URL');
        if (is_string($baseUrl) && $baseUrl !== '') {
            $config['base_url'] = $baseUrl;
        }

        $environment = getenv('SAAS_LAB_ENVIRONMENT');
        if (is_string($environment) && $environment !== '') {
            $config['environment'] = $environment;
        }

        $debug = getenv('SAAS_LAB_DEBUG');
        if ($debug === '1' || strtolower((string) $debug) === 'true') {
            $config['debug'] = true;
        } elseif ($debug === '0' || strtolower((string) $debug) === 'false') {
            $config['debug'] = false;
        }

        return $config;
    }

    private static function normalize(array $config): array
    {
        $config['base_url'] = rtrim((string) ($config['base_url'] ?? ''), '/');
        if ($config['base_url'] === '') {
            $config['base_url'] = '';
        }

        $config['debug'] = (bool) ($config['debug'] ?? false);
        $config['environment'] = (string) ($config['environment'] ?? 'production');

        if (!isset($config['session']) || !is_array($config['session'])) {
            $config['session'] = [];
        }

        return $config;
    }
}
