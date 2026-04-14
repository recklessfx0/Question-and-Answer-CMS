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
        <?php endif; ?>
        <?php if (in_array($type, ['question', 'answer', 'comment'], true)): ?>
            <form method="post" action="/moderator/<?= h((string) $type) ?>/<?= (int) $post['id'] ?>/delete">
                <?= $csrf->field('/moderator/' . (string) $type . '/' . (int) $post['id'] . '/delete') ?>
                <button type="submit">Delete <?= h((string) $type) ?></button>
            </form>
        <?php endif; ?>
    </div>
</section>
