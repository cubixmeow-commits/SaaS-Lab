<?php

declare(strict_types=1);

require_installed();
auth()->requireAdmin();

$errors = [];
$values = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'core_action' => 'item_created',
    'access_mode' => 'lab',
];

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $values['name'] = (string) ($_POST['name'] ?? '');
    $values['slug'] = (string) ($_POST['slug'] ?? '');
    $values['description'] = (string) ($_POST['description'] ?? '');
    $values['core_action'] = (string) ($_POST['core_action'] ?? 'item_created');
    $values['access_mode'] = (string) ($_POST['access_mode'] ?? 'lab');

    $factory = new ProjectFactory(app_root());
    $result = $factory->create(
        $values['name'],
        $values['slug'],
        $values['description'],
        $values['core_action'],
        $values['access_mode'],
        'logged-in-prototype'
    );

    if ($result['ok']) {
        flash('success', 'Project created.');
        redirect('/founder/projects/' . $result['project']->slug());
    }

    $errors = $result['errors'];
}

view('founder/project_new', [
    'title' => 'New project',
    'errors' => $errors,
    'values' => $values,
    'flashes' => flash_messages(),
]);
