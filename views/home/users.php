<?php declare(strict_types=1); ?>
<section class="page-head">
    <h1>Users</h1>
    <p>Browse community members.</p>
</section>
<section class="directory-grid">
    <?php foreach ($users as $user): ?>
        <a class="directory-card user-directory-card" href="/user/<?= h((string) $user['username']) ?>">
            <?php if (!empty($user['avatar_path'])): ?>
                <?= uploaded_image((string) $user['avatar_path'], 'avatar', (string) $user['username'] . ' avatar') ?>
            <?php endif; ?>
            <strong><?= h((string) $user['username']) ?></strong>
            <span><?= (int) $user['reputation'] ?> reputation &middot; <?= (int) $user['answer_count'] ?> answers</span>
        </a>
    <?php endforeach; ?>
</section>
