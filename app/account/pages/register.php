<?php

declare(strict_types=1);

require_installed();

if (auth()->check()) {
    redirect('/');
}

$errors = [];
$name = '';
$email = '';

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $name = (string) ($_POST['name'] ?? '');
    $email = (string) ($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!hash_equals($password, $confirmation)) {
        $errors[] = 'Password confirmation does not match.';
    } else {
        try {
            auth()->register($name, $email, $password);
            flash('success', 'Welcome to SaaS Lab.');
            redirect('/');
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            lab_log('error', 'Registration failed.', ['error' => $exception->getMessage()]);
            $errors[] = 'Unable to create your account right now. Please try again.';
        }
    }
}

view('account/register', [
    'title' => 'Register',
    'errors' => $errors,
    'name' => $name,
    'email' => $email,
    'flashes' => flash_messages(),
]);
