<?php

declare(strict_types=1);

require_installed();

if (auth()->check()) {
    redirect('/');
}

$errors = [];
$email = '';

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $email = (string) ($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (!auth()->login($email, $password)) {
        $errors[] = 'Invalid email or password.';
    } else {
        flash('success', 'Signed in.');
        redirect('/');
    }
}

view('account/login', [
    'title' => 'Sign in',
    'errors' => $errors,
    'email' => $email,
    'flashes' => flash_messages(),
]);
