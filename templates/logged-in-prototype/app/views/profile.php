<?php
/** @var Project $project */
/** @var array<string, mixed>|null $user */
/** @var list<array{type: string, message: string}> $flashes */
?>
<section class="panel">
    <p class="eyebrow"><?= e($project->name()) ?></p>
    <h1>Profile</h1>
    <p class="lede">Shared SaaS Lab account in use for this project. Edit your display name from the platform profile page.</p>
</section>

<?php foreach ($flashes as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<section class="panel">
    <dl class="detail-list">
        <div>
            <dt>Name</dt>
            <dd><?= e((string) ($user['name'] ?? '')) ?></dd>
        </div>
        <div>
            <dt>Email</dt>
            <dd><?= e((string) ($user['email'] ?? '')) ?></dd>
        </div>
        <div>
            <dt>Project</dt>
            <dd><?= e($project->name()) ?> · v<?= e($project->version()) ?></dd>
        </div>
    </dl>
    <div class="button-row">
        <a class="button" href="<?= e(url_path('/profile')) ?>">Edit platform profile</a>
        <a class="button button-primary" href="<?= e(url_path($project->url('dashboard'))) ?>">Back to dashboard</a>
    </div>
</section>
