<?php

declare(strict_types=1);

require_installed();
auth()->requireLogin();

$errors = [];
$user = auth()->user();

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $name = (string) ($_POST['name'] ?? '');
    try {
        auth()->updateDisplayName($name);
        flash('success', 'Profile updated.');
        redirect('/profile');
    } catch (InvalidArgumentException $exception) {
        $errors[] = $exception->getMessage();
    } catch (Throwable $exception) {
        lab_log('error', 'Profile update failed.', ['error' => $exception->getMessage()]);
        $errors[] = 'Unable to update your profile right now.';
    }
    $user = auth()->user();
}

view('account/profile', [
    'title' => 'Profile',
    'user' => $user,
    'errors' => $errors,
    'flashes' => flash_messages(),
]);
