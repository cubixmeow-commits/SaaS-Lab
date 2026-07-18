<?php
/** @var bool $alreadyInstalled */
/** @var list<array{key: string, label: string, ok: bool, detail: string}> $checks */
/** @var bool $checksPassed */
/** @var list<string> $errors */
/** @var list<array{type: string, message: string}> $flashes */
?>
<section class="install-hero">
    <p class="eyebrow">SaaS Lab</p>
    <h1>Install</h1>
    <p class="lede">Build. Test. Validate. Complete first-run setup for this Hostinger workspace.</p>
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
    <h2>Environment checks</h2>
    <ul class="check-list">
        <?php foreach ($checks as $check): ?>
            <li class="<?= $check['ok'] ? 'ok' : 'fail' ?>">
                <span class="status"><?= $check['ok'] ? 'OK' : 'Fix' ?></span>
                <div>
                    <strong><?= e($check['label']) ?></strong>
                    <p><?= e($check['detail']) ?></p>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php if ($alreadyInstalled): ?>
    <section class="panel">
        <h2>Installation complete</h2>
        <p>The installer lock is present. Remove <code>data/installed.lock</code> only if you intentionally reset this environment.</p>
        <p><a class="button" href="<?= e(url_path('/')) ?>">Continue</a></p>
    </section>
<?php elseif ($checksPassed): ?>
    <section class="panel">
        <h2>Create administrator</h2>
        <p>This account receives the Founder Dashboard role. Credentials are hashed and never stored in source files.</p>
        <form method="post" action="<?= e(url_path('/install')) ?>" class="stack-form">
            <?= csrf_field() ?>
            <label>
                Name
                <input type="text" name="name" required maxlength="120" autocomplete="name" value="<?= e((string) ($_POST['name'] ?? '')) ?>">
            </label>
            <label>
                Email
                <input type="email" name="email" required maxlength="190" autocomplete="username" value="<?= e((string) ($_POST['email'] ?? '')) ?>">
            </label>
            <label>
                Password
                <input type="password" name="password" required minlength="<?= (int) config('password_min_length', 8) ?>" autocomplete="new-password">
            </label>
            <label>
                Confirm password
                <input type="password" name="password_confirmation" required minlength="<?= (int) config('password_min_length', 8) ?>" autocomplete="new-password">
            </label>
            <button type="submit" class="button button-primary">Install SaaS Lab</button>
        </form>
    </section>
<?php else: ?>
    <section class="panel">
        <h2>Resolve the failed checks</h2>
        <p>On Hostinger: upload the repository, point the domain document root to <code>public/</code>, copy <code>config.local.example.php</code> to <code>config.local.php</code>, set <code>base_url</code>, and ensure <code>data/</code>, <code>storage/</code>, and <code>projects/</code> are writable.</p>
    </section>
<?php endif; ?>
