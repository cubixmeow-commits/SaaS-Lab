<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

auth()->requireLogin();

$errors = [];

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create');

    if ($action === 'create') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($title === '') {
            $errors[] = 'Title is required.';
        } else {
            $now = utc_now();
            $result = project_db()->run(
                'INSERT INTO items (user_id, title, notes, status, created_at, updated_at)
                 VALUES (:user_id, :title, :notes, :status, :created_at, :updated_at)',
                [
                    'user_id' => auth()->id(),
                    'title' => $title,
                    'notes' => $notes,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $itemId = (int) $result->lastInsertId();
            lab_event(project()->coreAction(), [
                'item_id' => $itemId,
            ]);

            flash('success', 'Item created.');
            redirect(project()->url('dashboard'));
        }
    }

    if ($action === 'complete' || $action === 'archive' || $action === 'delete') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $item = project_db()->fetchOne(
            'SELECT * FROM items WHERE id = :id AND user_id = :user_id LIMIT 1',
            ['id' => $itemId, 'user_id' => auth()->id()]
        );

        if ($item === null) {
            flash('error', 'Item not found.');
            redirect(project()->url('dashboard'));
        }

        if ($action === 'delete') {
            project_db()->run(
                'DELETE FROM items WHERE id = :id AND user_id = :user_id',
                ['id' => $itemId, 'user_id' => auth()->id()]
            );
            flash('success', 'Item deleted.');
        } else {
            $status = $action === 'complete' ? 'completed' : 'archived';
            project_db()->run(
                'UPDATE items SET status = :status, updated_at = :updated_at WHERE id = :id AND user_id = :user_id',
                [
                    'status' => $status,
                    'updated_at' => utc_now(),
                    'id' => $itemId,
                    'user_id' => auth()->id(),
                ]
            );
            flash('success', $action === 'complete' ? 'Item marked completed.' : 'Item archived.');
        }

        redirect(project()->url('dashboard'));
    }
}

$items = project_db()->fetchAll(
    'SELECT *
     FROM items
     WHERE user_id = :user_id
     ORDER BY created_at DESC',
    ['user_id' => auth()->id()]
);

project_view('dashboard', [
    'title' => project()->name(),
    'project' => project(),
    'user' => auth()->user(),
    'items' => $items,
    'errors' => $errors,
    'flashes' => flash_messages(),
]);
