<?php
/** @var array<string, mixed>|null $user */
/** @var list<string> $errors */
/** @var list<array{type: string, message: string}> $flashes */
$user = $user ?? [];
?>
<section class="panel">
    <p class="eyebrow">Account</p>
    <h1>Profile</h1>
    <p class="lede">View your shared account details. Email changes and password resets are out of V1 scope.</p>
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
    <dl class="detail-list">
        <div>
            <dt>Email</dt>
            <dd><?= e((string) ($user['email'] ?? '')) ?></dd>
        </div>
        <div>
            <dt>Role</dt>
            <dd><span class="status-label"><?= e((string) ($user['role'] ?? 'member')) ?></span></dd>
        </div>
        <div>
            <dt>Status</dt>
            <dd><span class="status-label"><?= e((string) ($user['status'] ?? 'active')) ?></span></dd>
        </div>
    </dl>

    <form method="post" action="<?= e(url_path('/profile')) ?>" class="stack-form">
        <?= csrf_field() ?>
        <label>
            Display name
            <input type="text" name="name" required maxlength="120" autocomplete="name" value="<?= e((string) ($user['name'] ?? '')) ?>">
        </label>
        <button type="submit" class="button button-primary">Save name</button>
    </form>
</section>
