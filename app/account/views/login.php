<?php
/** @var list<string> $errors */
/** @var string $email */
/** @var list<array{type: string, message: string}> $flashes */
?>
<section class="panel">
    <p class="eyebrow">Account</p>
    <h1>Sign in</h1>
    <p class="lede">Use your shared SaaS Lab credentials for the Founder Dashboard and every lab project.</p>
</section>

<?php foreach ($flashes as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<?php if ($errors !== []): ?>
    <div class="flash flash-error">
        <ul class="plain-list">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="panel">
    <form method="post" action="<?= e(url_path('/login')) ?>" class="stack-form">
        <?= csrf_field() ?>
        <label>
            Email
            <input type="email" name="email" required maxlength="190" autocomplete="username" value="<?= e($email) ?>">
        </label>
        <label>
            Password
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="button button-primary">Sign in</button>
    </form>
    <p class="form-footer muted">No account yet? <a href="<?= e(url_path('/register')) ?>">Register</a></p>
</section>
