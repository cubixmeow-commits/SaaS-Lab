<?php

declare(strict_types=1);

require_installed();
auth()->requireAdmin();

$slug = (string) ($GLOBALS['founder_project_slug'] ?? '');
$project = Project::findBySlug($slug);
if ($project === null) {
    http_response_code(404);
    view('shared/errors/404', ['title' => 'Not found']);
    exit;
}

$metrics = null;
foreach (FounderMetrics::projectRows() as $row) {
    if ((int) $row['project']['id'] === $project->id()) {
        $metrics = $row;
        break;
    }
}

view('founder/project_show', [
    'title' => $project->name(),
    'project' => $project,
    'metrics' => $metrics,
    'flashes' => flash_messages(),
]);
