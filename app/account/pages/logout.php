<?php

declare(strict_types=1);

require_installed();

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    // Prefer POST + CSRF for logout; GET falls back safely without side effects beyond redirect.
    if (auth()->check()) {
        view('account/logout', [
            'title' => 'Sign out',
            'flashes' => flash_messages(),
        ]);
        return;
    }
    redirect('/login');
}

auth()->logout();
flash('success', 'Signed out.');
redirect('/login');
