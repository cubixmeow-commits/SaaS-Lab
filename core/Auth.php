<?php

declare(strict_types=1);

final class Auth
{
    private const SESSION_USER_ID = 'auth_user_id';
    private const SESSION_VISIT_TOKEN = 'lab_visit_token';
    private const SESSION_OPENED_PROJECTS = 'lab_opened_projects';

    private static ?self $instance = null;

    /** @var array<string, mixed>|null|false */
    private array|null|false $userCache = false;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Register a new member account and log them in.
     *
     * @throws InvalidArgumentException on validation failure
     * @throws RuntimeException on persistence failure
     */
    public function register(string $name, string $email, string $password): int
    {
        $name = trim($name);
        $email = $this->normalizeEmail($email);
        $minLength = (int) Config::get('password_min_length', 8);

        if ($name === '') {
            throw new InvalidArgumentException('Name is required.');
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid email address is required.');
        }
        if (strlen($password) < $minLength) {
            throw new InvalidArgumentException('Password must be at least ' . $minLength . ' characters.');
        }

        $existing = platform_db()->fetchOne(
            'SELECT id FROM users WHERE email = :email LIMIT 1',
            ['email' => $email]
        );
        if ($existing !== null) {
            // Generic messaging — do not reveal whether the email is registered.
            throw new InvalidArgumentException('Unable to create that account. Try a different email or sign in.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Unable to hash password.');
        }

        $now = utc_now();
        $result = platform_db()->run(
            'INSERT INTO users (name, email, password_hash, role, status, created_at, updated_at)
             VALUES (:name, :email, :password_hash, :role, :status, :created_at, :updated_at)',
            [
                'name' => $name,
                'email' => $email,
                'password_hash' => $hash,
                'role' => 'member',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $userId = (int) $result->lastInsertId();
        $this->establishAuthenticatedSession($userId);

        return $userId;
    }

    public function login(string $email, string $password): bool
    {
        $email = $this->normalizeEmail($email);
        if ($email === '' || $password === '') {
            return false;
        }

        $row = platform_db()->fetchOne(
            'SELECT id, password_hash, status FROM users WHERE email = :email LIMIT 1',
            ['email' => $email]
        );

        if ($row === null) {
            // Constant-time-ish dummy verify to reduce timing oracle sharpness.
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            return false;
        }

        if (($row['status'] ?? '') === 'suspended') {
            return false;
        }

        if (!password_verify($password, (string) ($row['password_hash'] ?? ''))) {
            return false;
        }

        $userId = (int) $row['id'];
        $this->establishAuthenticatedSession($userId);

        platform_db()->run(
            'UPDATE users SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id',
            [
                'last_login_at' => utc_now(),
                'updated_at' => utc_now(),
                'id' => $userId,
            ]
        );

        return true;
    }

    public function logout(): void
    {
        Session::start();
        unset(
            $_SESSION[self::SESSION_USER_ID],
            $_SESSION[self::SESSION_VISIT_TOKEN],
            $_SESSION[self::SESSION_OPENED_PROJECTS]
        );
        $this->userCache = null;
        Session::destroy();
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user['id'];
    }

    public function user(): ?array
    {
        if ($this->userCache !== false) {
            return $this->userCache;
        }

        Session::start();
        $userId = $_SESSION[self::SESSION_USER_ID] ?? null;
        if (!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) {
            $this->userCache = null;
            return null;
        }

        $userId = (int) $userId;
        $row = platform_db()->fetchOne(
            'SELECT id, name, email, role, status, created_at, updated_at, last_login_at
             FROM users
             WHERE id = :id
             LIMIT 1',
            ['id' => $userId]
        );

        if ($row === null || ($row['status'] ?? '') !== 'active') {
            // Suspended or missing users lose the session.
            unset($_SESSION[self::SESSION_USER_ID]);
            $this->userCache = null;
            return null;
        }

        $this->userCache = $row;

        return $this->userCache;
    }

    public function requireLogin(): void
    {
        if (!$this->check()) {
            flash('warning', 'Please sign in to continue.');
            redirect('/login');
        }
    }

    public function requireAdmin(): void
    {
        $this->requireLogin();
        $user = $this->user();
        if (($user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            view('shared/errors/403', [
                'title' => 'Forbidden',
            ]);
            exit;
        }
    }

    public function isAdmin(): bool
    {
        $user = $this->user();

        return $user !== null && ($user['role'] ?? '') === 'admin';
    }

    /**
     * Project access rules for V1 lab/archived modes.
     * Full Project class arrives in Phase 4; accept array-shaped project rows for now.
     *
     * @param object|array{access_mode?: string} $project
     */
    public function canAccessProject(object|array $project): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $accessMode = is_object($project)
            ? (string) (method_exists($project, 'accessMode') ? $project->accessMode() : ($project->access_mode ?? ''))
            : (string) ($project['access_mode'] ?? '');

        return match ($accessMode) {
            'lab' => true,
            'archived' => ($user['role'] ?? '') === 'admin',
            // private/public reserved for later; deny by default in V1.
            default => ($user['role'] ?? '') === 'admin',
        };
    }

    public function updateDisplayName(string $name): void
    {
        $this->requireLogin();
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Name is required.');
        }

        $userId = $this->id();
        if ($userId === null) {
            throw new RuntimeException('Not authenticated.');
        }

        platform_db()->run(
            'UPDATE users SET name = :name, updated_at = :updated_at WHERE id = :id',
            [
                'name' => $name,
                'updated_at' => utc_now(),
                'id' => $userId,
            ]
        );

        $this->userCache = false;
    }

    /**
     * Opaque visit token for event correlation — not PHP session_id().
     * Survives session_regenerate_id after login.
     */
    public function visitToken(): ?string
    {
        if (!$this->check()) {
            return null;
        }

        Session::start();
        $token = $_SESSION[self::SESSION_VISIT_TOKEN] ?? null;
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(16));
            $_SESSION[self::SESSION_VISIT_TOKEN] = $token;
        }

        return $token;
    }

    public function markProjectOpened(string $slug): bool
    {
        Session::start();
        if (!isset($_SESSION[self::SESSION_OPENED_PROJECTS]) || !is_array($_SESSION[self::SESSION_OPENED_PROJECTS])) {
            $_SESSION[self::SESSION_OPENED_PROJECTS] = [];
        }

        if (!empty($_SESSION[self::SESSION_OPENED_PROJECTS][$slug])) {
            return false;
        }

        $_SESSION[self::SESSION_OPENED_PROJECTS][$slug] = true;

        return true;
    }

    public function hasOpenedProject(string $slug): bool
    {
        Session::start();
        $opened = $_SESSION[self::SESSION_OPENED_PROJECTS] ?? [];

        return is_array($opened) && !empty($opened[$slug]);
    }

    private function establishAuthenticatedSession(int $userId): void
    {
        Session::start();

        // Preserve visit token across regenerate when already present (re-login edge cases).
        $existingVisitToken = $_SESSION[self::SESSION_VISIT_TOKEN] ?? null;

        Session::regenerate();

        $_SESSION[self::SESSION_USER_ID] = $userId;

        if (is_string($existingVisitToken) && $existingVisitToken !== '') {
            $_SESSION[self::SESSION_VISIT_TOKEN] = $existingVisitToken;
        } else {
            $_SESSION[self::SESSION_VISIT_TOKEN] = bin2hex(random_bytes(16));
        }

        // New authenticated visit starts with a clean opened-projects set.
        $_SESSION[self::SESSION_OPENED_PROJECTS] = [];

        $this->userCache = false;
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
