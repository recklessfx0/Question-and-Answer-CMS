<?php declare(strict_types=1); ?>
<section class="page-head">
    <h1>Notifications</h1>
    <form method="post" action="/notifications/read" class="inline">
        <?= $csrf->field('/notifications/read') ?>
        <button type="submit">Mark all read</button>
    </form>
</section>
<section class="question-list">
    <?php if ($notifications === []): ?>
        <p>No notifications yet.</p>
    <?php endif; ?>
    <?php foreach ($notifications as $notification): ?>
        <?php $payload = is_array($notification['payload_data'] ?? null) ? $notification['payload_data'] : ['message' => 'You have an update.', 'url' => '']; ?>
        <article class="question-row">
            <div class="score"><?= $notification['read_at'] === null ? 'New' : 'Read' ?></div>
            <div>
                <h2><?= h((string) $notification['type']) ?></h2>
                <p><?= h((string) ($payload['message'] ?? 'You have an update.')) ?></p>
                <?php if (!empty($payload['url'])): ?>
                    <p><a href="<?= h((string) $payload['url']) ?>">Open</a></p>
                <?php endif; ?>
                <p class="meta"><?= h((string) $notification['created_at']) ?></p>
            </div>
        </article>
    <?php endforeach; ?>
</section>
