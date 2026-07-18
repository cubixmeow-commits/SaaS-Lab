<?php
/** @var list<array{type: string, message: string}> $flashes */
?>
<section class="panel">
    <p class="eyebrow">SaaS Lab</p>
    <h1>Build. Test. Validate.</h1>
    <p class="lede">Platform foundation is installed. Account routes and the Founder Dashboard arrive in later phases.</p>
    <?php foreach ($flashes as $flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
    <p class="muted">Phase 1 complete — authentication is next.</p>
</section>
