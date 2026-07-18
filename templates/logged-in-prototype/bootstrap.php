<?php

declare(strict_types=1);

/**
 * Generated project bootstrap.
 * Safe to require from every project page.
 */

$labRoot = dirname(__DIR__, 2);

if (!function_exists('auth')) {
    require $labRoot . '/core/bootstrap.php';
}

$slug = basename(__DIR__);

if (!ProjectContext::has()) {
    $project = Project::findBySlug($slug);
    if ($project === null) {
        http_response_code(404);
        echo 'Project not found.';
        exit;
    }

    auth()->requireLogin();
    if (!auth()->canAccessProject($project)) {
        http_response_code(403);
        echo 'Forbidden.';
        exit;
    }

    ProjectContext::set($project);
    $project->ensureMigrations();
    EventLogger::maybeProjectOpened();
}
