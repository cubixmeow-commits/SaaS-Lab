<?php
/** @var list<string> $errors */
/** @var string $name */
/** @var string $email */
/** @var list<array{type: string, message: string}> $flashes */
?>
<section class="panel">
    <p class="eyebrow">Account</p>
    <h1>Create account</h1>
    <p class="lede">Register once. The same member account works across every active lab project.</p>
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
    <form method="post" action="<?= e(url_path('/register')) ?>" class="stack-form">
        <?= csrf_field() ?>
        <label>
            Name
            <input type="text" name="name" required maxlength="120" autocomplete="name" value="<?= e($name) ?>">
        </label>
        <label>
            Email
            <input type="email" name="email" required maxlength="190" autocomplete="username" value="<?= e($email) ?>">
        </label>
        <label>
            Password
            <input type="password" name="password" required minlength="<?= (int) config('password_min_length', 8) ?>" autocomplete="new-password">
        </label>
        <label>
            Confirm password
            <input type="password" name="password_confirmation" required minlength="<?= (int) config('password_min_length', 8) ?>" autocomplete="new-password">
        </label>
        <button type="submit" class="button button-primary">Register</button>
    </form>
    <p class="form-footer muted">Already have an account? <a href="<?= e(url_path('/login')) ?>">Sign in</a></p>
</section>
