<?php
/** @var array{members: int, active_projects: int, events_7d: int} $summary */
/** @var list<array<string, mixed>> $rows */
/** @var list<array{type: string, message: string}> $flashes */
?>
<section class="panel">
    <p class="eyebrow">Founder</p>
    <h1>Dashboard</h1>
    <p class="lede">Launch experiments and watch shared usage signals.</p>
    <div class="button-row">
        <a class="button button-primary" href="<?= e(url_path('/founder/projects/new')) ?>">New project</a>
        <a class="button" href="<?= e(url_path('/founder/users')) ?>">Users</a>
    </div>
</section>

<?php foreach ($flashes as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<section class="metrics-strip">
    <div class="metric">
        <span class="metric-label">Members</span>
        <strong><?= (int) $summary['members'] ?></strong>
    </div>
    <div class="metric">
        <span class="metric-label">Active projects</span>
        <strong><?= (int) $summary['active_projects'] ?></strong>
    </div>
    <div class="metric">
        <span class="metric-label">Events 7d</span>
        <strong><?= (int) $summary['events_7d'] ?></strong>
    </div>
</section>

<section class="panel">
    <h2>Projects</h2>
    <?php if ($rows === []): ?>
        <p class="muted">No projects yet. Create Health Rival to begin the acceptance loop.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Version</th>
                        <th>Users</th>
                        <th>Active 7d</th>
                        <th>Core actions 7d</th>
                        <th>Last activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $project = $row['project']; ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $project['name']) ?></strong>
                                <div class="muted"><?= e((string) $project['slug']) ?> · <?= e((string) $project['access_mode']) ?></div>
                            </td>
                            <td>v<?= e((string) $project['current_version']) ?></td>
                            <td><?= (int) $row['users'] ?></td>
                            <td><?= (int) $row['active_7d'] ?></td>
                            <td><?= (int) $row['core_actions_7d'] ?></td>
                            <td><?= e(format_app_time($row['last_activity'] !== null ? (string) $row['last_activity'] : null)) ?></td>
                            <td>
                                <div class="button-row compact">
                                    <a class="button" href="<?= e(url_path('/p/' . $project['slug'])) ?>">Open</a>
                                    <a class="button" href="<?= e(url_path('/founder/projects/' . $project['slug'])) ?>">Manage</a>
                                    <?php if (($project['access_mode'] ?? '') !== 'archived'): ?>
                                        <form method="post" action="<?= e(url_path('/founder/projects/' . $project['slug'] . '/archive')) ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="button">Archive</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
