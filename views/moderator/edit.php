<?php declare(strict_types=1); ?>
<section class="narrow">
    <h1>Edit <?= h((string) $type) ?></h1>
    <form method="post" action="/moderator/<?= h((string) $type) ?>/<?= (int) $post['id'] ?>/edit">
        <?= $csrf->field('/moderator/' . (string) $type . '/' . (int) $post['id'] . '/edit') ?>
        <?php if ($type === 'question'): ?>
            <label>Title <input name="title" value="<?= h((string) $post['title']) ?>" maxlength="180" required></label>
        <?php endif; ?>
        <label>Body <textarea name="body" rows="12" required><?= h((string) $post['body']) ?></textarea></label>
        <button type="submit">Save changes</button>
    </form>
    <div class="moderation-actions edit-actions">
        <?php if ($type === 'question'): ?>
            <form method="post" action="/moderator/question/<?= (int) $post['id'] ?>/close">
                <?= $csrf->field('/moderator/question/' . (int) $post['id'] . '/close') ?>
                <button type="submit">Close question</button>
            </form>
            <form method="post" action="/moderator/question/<?= (int) $post['id'] ?>/duplicate" class="duplicate-form">
                <?= $csrf->field('/moderator/question/' . (int) $post['id'] . '/duplicate') ?>
                <label>Canonical question ID
                    <input name="duplicate_of_id" type="number" min="0" value="<?= (int) ($post['duplicate_of_id'] ?? 0) ?>" placeholder="0 clears duplicate">
                </label>
                <?php if (!empty($post['duplicate_slug'])): ?>
                    <p class="meta">Current canonical: <a href="/question/<?= h((string) $post['duplicate_slug']) ?>"><?= h((string) ($post['duplicate_title'] ?? $post['duplicate_slug'])) ?></a></p>
                <?php endif; ?>
                <button type="submit">Update duplicate canonical</button>
            </form>
        <?php endif; ?>
        <?php if (in_array($type, ['question', 'answer', 'comment'], true)): ?>
            <form method="post" action="/moderator/<?= h((string) $type) ?>/<?= (int) $post['id'] ?>/delete">
                <?= $csrf->field('/moderator/' . (string) $type . '/' . (int) $post['id'] . '/delete') ?>
                <button type="submit">Delete <?= h((string) $type) ?></button>
            </form>
        <?php endif; ?>
    </div>
</section>
