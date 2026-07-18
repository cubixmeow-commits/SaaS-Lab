<?php
/** @var array<string, mixed>|null $user */
/** @var list<array{type: string, message: string}> $flashes */
?>
<section class="panel">
    <p class="eyebrow">SaaS Lab</p>
    <h1>Build. Test. Validate.</h1>
    <p class="lede">Shared authentication is online. Project launching and the Founder Dashboard arrive in later phases.</p>
</section>

<?php foreach ($flashes as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<section class="panel">
    <?php if ($user !== null): ?>
        <h2>Signed in as <?= e((string) $user['name']) ?></h2>
        <p class="muted"><?= e((string) $user['email']) ?> · <?= e((string) $user['role']) ?></p>
        <div class="button-row">
            <a class="button button-primary" href="<?= e(url_path('/profile')) ?>">Profile</a>
            <a class="button" href="<?= e(url_path('/logout')) ?>">Sign out</a>
        </div>
    <?php else: ?>
        <h2>Get started</h2>
        <p class="muted">Create a member account or sign in with the administrator created during install.</p>
        <div class="button-row">
            <a class="button button-primary" href="<?= e(url_path('/register')) ?>">Register</a>
            <a class="button" href="<?= e(url_path('/login')) ?>">Sign in</a>
        </div>
    <?php endif; ?>
</section>
