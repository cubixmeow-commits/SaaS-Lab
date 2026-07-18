<?php

declare(strict_types=1);

final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $name = (string) Config::get('session.name', 'saas_lab_session');
        $lifetime = (int) Config::get('session.lifetime', 86400);
        $secure = (bool) Config::get('session.secure', true);
        $httponly = (bool) Config::get('session.httponly', true);
        $samesite = (string) Config::get('session.samesite', 'Lax');
        $path = Config::sessionCookiePath();

        // When the request is clearly HTTP (local CLI/dev), avoid Secure-only cookies
        // that browsers would refuse to store.
        if (!$secure && self::requestLooksHttps()) {
            $secure = true;
        }
        if ($secure && !self::requestLooksHttps() && Config::get('environment') !== 'production') {
            $secure = false;
        }

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $path,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);

        if (!session_start()) {
            throw new RuntimeException('Unable to start session.');
        }

        self::$started = true;
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        self::start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
        self::$started = false;
    }

    /**
     * Persist session data before a redirect terminates the request.
     */
    public static function persist(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
            self::$started = false;
        }
    }

    private static function requestLooksHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if (is_string($forwarded) && strtolower($forwarded) === 'https') {
            return true;
        }

        return false;
    }
}
