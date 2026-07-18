<?php

declare(strict_types=1);

function app_root(): string
{
    return dirname(__DIR__);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function base_path(string $path = ''): string
{
    $root = app_root();
    if ($path === '') {
        return $root;
    }

    return $root . '/' . ltrim($path, '/');
}

function url_path(string $path = '/'): string
{
    $base = (string) config('base_url', '');
    $basePath = '';
    if ($base !== '') {
        $parts = parse_url($base);
        $basePath = rtrim($parts['path'] ?? '', '/');
    }

    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }

    return $basePath . $path;
}

function redirect(string $path): never
{
    if (preg_match('#^(https?:)?//#i', $path) === 1) {
        throw new InvalidArgumentException('External redirects are not allowed via redirect().');
    }

    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }

    Session::persist();
    header('Location: ' . url_path($path));
    exit;
}

function flash(string $type, string $message): void
{
    Session::start();
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        $_SESSION['_flash'] = [];
    }
    $_SESSION['_flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function flash_messages(): array
{
    Session::start();
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    return is_array($messages) ? $messages : [];
}

function csrf_token(): string
{
    Session::start();
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf_or_fail(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $sessionToken = csrf_token();

    if (!is_string($token) || !hash_equals($sessionToken, $token)) {
        http_response_code(419);
        lab_log('warning', 'CSRF validation failed.', [
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
        ]);
        echo 'Invalid security token. Please go back and try again.';
        exit;
    }
}

function platform_db(): Database
{
    return Database::platform(app_root());
}

function project_db(): Database
{
    return ProjectContext::database();
}

function project(): Project
{
    return ProjectContext::project();
}

function current_project_slug(): string
{
    return project()->slug();
}

function lab_event(string $eventName, array $data = []): void
{
    EventLogger::log($eventName, $data);
}

function lab_log(string $level, string $message, array $context = []): void
{
    $line = '[' . gmdate('c') . '] ' . strtoupper($level) . ' ' . $message;
    if ($context !== []) {
        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES);
        if ($encoded !== false) {
            $line .= ' ' . $encoded;
        }
    }
    $line .= PHP_EOL;

    $logDir = app_root() . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $logFile = $logDir . '/app.log';
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

    if ((bool) config('debug', false)) {
        error_log(rtrim($line));
    }
}

function is_installed(): bool
{
    return is_file(app_root() . '/data/installed.lock');
}

function utc_now(): string
{
    return gmdate('c');
}

function format_app_time(?string $utcIso): string
{
    if ($utcIso === null || $utcIso === '') {
        return '—';
    }

    try {
        $dt = new DateTimeImmutable($utcIso);
        $timezone = (string) config('app_timezone', 'UTC');
        $dt = $dt->setTimezone(new DateTimeZone($timezone));

        return $dt->format('Y-m-d H:i T');
    } catch (Throwable) {
        return $utcIso;
    }
}

function view(string $name, array $data = [], ?string $layout = 'main'): void
{
    View::render(app_root(), $name, $data, $layout);
}

function project_view(string $name, array $data = [], ?string $layout = 'main'): void
{
    $project = project();
    $viewFile = $project->directory() . '/app/views/' . $name . '.php';
    if (!is_file($viewFile)) {
        throw new RuntimeException('Project view not found: ' . $name);
    }

    extract($data, EXTR_SKIP);
    ob_start();
    require $viewFile;
    $content = (string) ob_get_clean();

    if ($layout === null) {
        echo $content;
        return;
    }

    $layoutFile = app_root() . '/app/shared/views/layouts/' . $layout . '.php';
    if (!is_file($layoutFile)) {
        echo $content;
        return;
    }

    require $layoutFile;
}

function auth(): Auth
{
    return Auth::instance();
}

function require_installed(): void
{
    if (!is_installed()) {
        redirect('/install');
    }
}
