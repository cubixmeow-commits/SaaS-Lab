<?php

declare(strict_types=1);

require_installed();
auth()->requireLogin();

$projects = platform_db()->fetchAll(
    "SELECT slug, name, description, current_version, updated_at
     FROM projects
     WHERE access_mode = 'lab'
     ORDER BY name ASC"
);

view('account/projects', [
    'title' => 'Projects',
    'projects' => $projects,
    'flashes' => flash_messages(),
]);
