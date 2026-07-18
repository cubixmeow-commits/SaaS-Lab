<?php
/** @var list<array{type: string, message: string}> $flashes */
?>
<section class="panel">
    <p class="eyebrow">Account</p>
    <h1>Sign out</h1>
    <p class="lede">End this SaaS Lab session. Your visit token and opened-project markers will be cleared.</p>
</section>

<?php foreach ($flashes as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<section class="panel">
    <form method="post" action="<?= e(url_path('/logout')) ?>" class="stack-form">
        <?= csrf_field() ?>
        <button type="submit" class="button button-primary">Confirm sign out</button>
    </form>
</section>
