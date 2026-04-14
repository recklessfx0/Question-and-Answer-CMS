<?php declare(strict_types=1); ?>
<section class="page-head">
    <h1>Tags</h1>
    <p>Find questions by topic.</p>
</section>
<section class="directory-grid">
    <?php foreach ($tags as $tag): ?>
        <a class="directory-card" href="/tag/<?= h((string) $tag['slug']) ?>">
            <strong>#<?= h((string) $tag['name']) ?></strong>
            <span><?= (int) $tag['question_count'] ?> questions</span>
        </a>
    <?php endforeach; ?>
</section>
