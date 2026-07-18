<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

auth()->requireLogin();

project_view('profile', [
    'title' => 'Profile · ' . project()->name(),
    'project' => project(),
    'user' => auth()->user(),
    'flashes' => flash_messages(),
]);
