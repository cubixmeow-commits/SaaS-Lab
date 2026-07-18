<?php
/** @var Project $project */
/** @var array<string, mixed>|null $metrics */
/** @var list<array{type: string, message: string}> $flashes */
?>
<section class="panel">
    <p class="eyebrow">Founder</p>
    <h1><?= e($project->name()) ?></h1>
    <p class="lede"><?= e($project->description()) ?></p>
    <div class="button-row">
        <a class="button button-primary" href="<?= e(url_path($project->url())) ?>">Open</a>
        <a class="button" href="<?= e(url_path('/founder')) ?>">Back</a>
        <?php if ($project->accessMode() !== 'archived'): ?>
            <form method="post" action="<?= e(url_path('/founder/projects/' . $project->slug() . '/archive')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="button">Archive</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php foreach ($flashes as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<section class="panel">
    <dl class="detail-list">
        <div><dt>Slug</dt><dd><?= e($project->slug()) ?></dd></div>
        <div><dt>Version</dt><dd>v<?= e($project->version()) ?></dd></div>
        <div><dt>Template</dt><dd><?= e($project->templateKey()) ?></dd></div>
        <div><dt>Core action</dt><dd><code><?= e($project->coreAction()) ?></code></dd></div>
        <div><dt>Access mode</dt><dd><span class="status-label"><?= e($project->accessMode()) ?></span></dd></div>
        <?php if ($metrics !== null): ?>
            <div><dt>Users</dt><dd><?= (int) $metrics['users'] ?></dd></div>
            <div><dt>Active 7d</dt><dd><?= (int) $metrics['active_7d'] ?></dd></div>
            <div><dt>Core actions 7d</dt><dd><?= (int) $metrics['core_actions_7d'] ?></dd></div>
            <div><dt>Last activity</dt><dd><?= e(format_app_time($metrics['last_activity'] !== null ? (string) $metrics['last_activity'] : null)) ?></dd></div>
        <?php endif; ?>
    </dl>
</section>
