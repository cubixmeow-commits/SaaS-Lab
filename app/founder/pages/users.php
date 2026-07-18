<?php

declare(strict_types=1);

require_installed();
auth()->requireAdmin();

$errors = [];

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $target = platform_db()->fetchOne(
        'SELECT id, role, status FROM users WHERE id = :id LIMIT 1',
        ['id' => $userId]
    );

    if ($target === null) {
        $errors[] = 'User not found.';
    } elseif (($target['role'] ?? '') === 'admin') {
        $errors[] = 'Administrator accounts cannot be suspended from this screen.';
    } elseif ($action === 'suspend') {
        platform_db()->run(
            'UPDATE users SET status = :status, updated_at = :updated_at WHERE id = :id',
            ['status' => 'suspended', 'updated_at' => utc_now(), 'id' => $userId]
        );
        flash('success', 'Member suspended.');
        redirect('/founder/users');
    } elseif ($action === 'reactivate') {
        platform_db()->run(
            'UPDATE users SET status = :status, updated_at = :updated_at WHERE id = :id',
            ['status' => 'active', 'updated_at' => utc_now(), 'id' => $userId]
        );
        flash('success', 'Member reactivated.');
        redirect('/founder/users');
    } else {
        $errors[] = 'Unknown action.';
    }
}

$users = platform_db()->fetchAll(
    'SELECT id, name, email, role, status, created_at, last_login_at
     FROM users
     ORDER BY created_at ASC'
);

view('founder/users', [
    'title' => 'Users',
    'users' => $users,
    'errors' => $errors,
    'flashes' => flash_messages(),
]);
