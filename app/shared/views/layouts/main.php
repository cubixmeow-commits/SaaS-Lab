<?php
/** @var string $content */
/** @var string|null $title */
$pageTitle = trim(($title ?? '') !== '' ? (string) $title . ' · ' : '') . (string) config('app_name', 'SaaS Lab');
$currentUser = is_installed() ? auth()->user() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(url_path('/assets/app.css')) ?>">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="<?= e(url_path('/')) ?>">SaaS Lab</a>
        <nav class="site-nav" aria-label="Primary">
            <?php if (!is_installed()): ?>
                <a href="<?= e(url_path('/install')) ?>">Install</a>
            <?php elseif ($currentUser !== null): ?>
                <a href="<?= e(url_path('/profile')) ?>">Profile</a>
                <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                    <span class="nav-meta">Admin</span>
                <?php endif; ?>
                <a href="<?= e(url_path('/logout')) ?>">Sign out</a>
            <?php else: ?>
                <a href="<?= e(url_path('/login')) ?>">Sign in</a>
                <a href="<?= e(url_path('/register')) ?>">Register</a>
            <?php endif; ?>
        </nav>
    </header>
    <main class="site-main">
        <?= $content ?>
    </main>
    <footer class="site-footer">
        <span>SaaS Lab</span>
        <span class="muted">Build. Test. Validate.</span>
    </footer>
    <script src="<?= e(url_path('/assets/app.js')) ?>" defer></script>
</body>
</html>
