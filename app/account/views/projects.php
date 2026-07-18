<?php
/** @var list<array<string, mixed>> $projects */
/** @var list<array{type: string, message: string}> $flashes */
?>
<section class="panel">
    <p class="eyebrow">Experiments</p>
    <h1>Projects</h1>
    <p class="lede">Every active lab project is available to authenticated members.</p>
</section>

<?php foreach ($flashes as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<section class="panel">
    <?php if ($projects === []): ?>
        <p class="muted">No active lab projects yet. An administrator can create one from the Founder Dashboard.</p>
    <?php else: ?>
        <ul class="project-list">
            <?php foreach ($projects as $project): ?>
                <li class="project-row">
                    <div>
                        <strong><?= e((string) $project['name']) ?></strong>
                        <p class="muted"><?= e((string) $project['description']) ?></p>
                        <p class="meta">
                            <span class="status-label">v<?= e((string) $project['current_version']) ?></span>
                            <span class="muted">Updated <?= e(format_app_time((string) $project['updated_at'])) ?></span>
                        </p>
                    </div>
                    <div class="button-row">
                        <a class="button button-primary" href="<?= e(url_path('/p/' . $project['slug'])) ?>">Open Project</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
