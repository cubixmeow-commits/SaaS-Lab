<?php
/** @var list<array<string, mixed>> $users */
/** @var list<string> $errors */
/** @var list<array{type: string, message: string}> $flashes */
?>
<section class="panel">
    <p class="eyebrow">Founder</p>
    <h1>Users</h1>
    <p class="lede">Registered accounts. Suspended members cannot sign in.</p>
    <div class="button-row">
        <a class="button" href="<?= e(url_path('/founder')) ?>">Dashboard</a>
    </div>
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
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= e((string) $user['name']) ?></td>
                        <td><?= e((string) $user['email']) ?></td>
                        <td><span class="status-label"><?= e((string) $user['role']) ?></span></td>
                        <td><span class="status-label"><?= e((string) $user['status']) ?></span></td>
                        <td><?= e(format_app_time(isset($user['last_login_at']) ? (string) $user['last_login_at'] : null)) ?></td>
                        <td>
                            <?php if (($user['role'] ?? '') === 'member'): ?>
                                <?php if (($user['status'] ?? '') === 'active'): ?>
                                    <form method="post" action="<?= e(url_path('/founder/users')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                        <input type="hidden" name="action" value="suspend">
                                        <button type="submit" class="button">Suspend</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= e(url_path('/founder/users')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                        <input type="hidden" name="action" value="reactivate">
                                        <button type="submit" class="button">Reactivate</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
