<?php
/** @var Project $project */
/** @var array<string, mixed>|null $user */
/** @var list<array<string, mixed>> $items */
/** @var list<string> $errors */
/** @var list<array{type: string, message: string}> $flashes */
?>
<section class="panel">
    <p class="eyebrow"><?= e($project->name()) ?></p>
    <h1>Dashboard</h1>
    <p class="lede">Create and manage your personal items. Data is isolated to this project and your account.</p>
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
    <h2>New item</h2>
    <form method="post" action="<?= e(url_path($project->url('dashboard'))) ?>" class="stack-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <label>
            Title
            <input type="text" name="title" required maxlength="200" value="<?= e((string) ($_POST['title'] ?? '')) ?>">
        </label>
        <label>
            Notes
            <textarea name="notes" rows="3" maxlength="2000"><?= e((string) ($_POST['notes'] ?? '')) ?></textarea>
        </label>
        <button type="submit" class="button button-primary">Create item</button>
    </form>
</section>

<section class="panel">
    <h2>Your items</h2>
    <?php if ($items === []): ?>
        <p class="muted">No items yet. Create one to emit the project core action.</p>
    <?php else: ?>
        <ul class="project-list">
            <?php foreach ($items as $item): ?>
                <li class="project-row">
                    <div>
                        <strong><?= e((string) $item['title']) ?></strong>
                        <p class="muted"><?= e((string) $item['notes']) ?></p>
                        <p class="meta">
                            <span class="status-label"><?= e((string) $item['status']) ?></span>
                            <span class="muted"><?= e(format_app_time((string) $item['created_at'])) ?></span>
                        </p>
                    </div>
                    <div class="button-row">
                        <?php if (($item['status'] ?? '') === 'active'): ?>
                            <form method="post" action="<?= e(url_path($project->url('dashboard'))) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="complete">
                                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="button">Complete</button>
                            </form>
                        <?php endif; ?>
                        <?php if (($item['status'] ?? '') !== 'archived'): ?>
                            <form method="post" action="<?= e(url_path($project->url('dashboard'))) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="archive">
                                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="button">Archive</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url_path($project->url('dashboard'))) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                            <button type="submit" class="button">Delete</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
