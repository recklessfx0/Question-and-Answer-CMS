<?php declare(strict_types=1); ?>
<section class="static-page">
    <header class="static-page-head">
        <p class="eyebrow"><?= h((string) $pageTitle) ?></p>
        <h1><?= h((string) $pageTitle) ?></h1>
        <p><?= h((string) $pageDescription) ?></p>
    </header>
    <div class="static-page-body">
        <?php foreach (preg_split('/\R{2,}/', trim((string) $pageBody)) ?: [] as $paragraph): ?>
            <?php if (trim($paragraph) !== ''): ?>
                <p><?= nl2br(h(trim($paragraph)), false) ?></p>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
