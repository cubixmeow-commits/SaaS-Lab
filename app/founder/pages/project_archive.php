<?php

declare(strict_types=1);

require_installed();
auth()->requireAdmin();

$slug = (string) ($GLOBALS['founder_project_slug'] ?? '');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    flash('error', 'Archive requires a confirmed POST.');
    redirect('/founder/projects/' . $slug);
}

$factory = new ProjectFactory(app_root());
if ($factory->archive($slug)) {
    flash('success', 'Project archived.');
} else {
    flash('error', 'Unable to archive that project.');
}

redirect('/founder');
