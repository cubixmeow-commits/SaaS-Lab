<?php
/** @var list<string> $errors */
/** @var array<string, string> $values */
/** @var list<array{type: string, message: string}> $flashes */
?>
<section class="panel">
    <p class="eyebrow">Founder</p>
    <h1>New project</h1>
    <p class="lede">Generate a project folder, SQLite database, and clean `/p/{slug}` URL from the approved template.</p>
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
    <form method="post" action="<?= e(url_path('/founder/projects/new')) ?>" class="stack-form">
        <?= csrf_field() ?>
        <label>
            Project name
            <input type="text" name="name" required maxlength="120" value="<?= e($values['name']) ?>">
        </label>
        <label>
            Project slug
            <input type="text" name="slug" required maxlength="80" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="<?= e($values['slug']) ?>">
        </label>
        <label>
            Description
            <textarea name="description" rows="3" maxlength="500"><?= e($values['description']) ?></textarea>
        </label>
        <label>
            Template
            <input type="text" value="logged-in-prototype" disabled>
        </label>
        <label>
            Core action name
            <input type="text" name="core_action" required maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" value="<?= e($values['core_action']) ?>">
        </label>
        <label>
            Access mode
            <select name="access_mode">
                <option value="lab" <?= $values['access_mode'] === 'lab' ? 'selected' : '' ?>>lab</option>
                <option value="private" <?= $values['access_mode'] === 'private' ? 'selected' : '' ?>>private</option>
                <option value="public" <?= $values['access_mode'] === 'public' ? 'selected' : '' ?>>public</option>
            </select>
        </label>
        <button type="submit" class="button button-primary">Create project</button>
    </form>
</section>
