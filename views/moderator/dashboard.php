<?php declare(strict_types=1); ?>
<section class="page-head">
    <h1>Moderator tools</h1>
    <p>Review open reports, edit content, delete unsafe posts, and close questions.</p>
</section>
<section class="table-wrap">
    <table>
        <thead><tr><th>Target</th><th>Reason</th><th>Reported by</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($flags as $flag): ?>
            <?php
            $type = (string) $flag['post_type'];
            $targetId = (int) $flag['post_id'];
            $targetTitle = (string) ($flag['target_title'] ?: '#' . $targetId);
            $targetSlug = (string) ($flag['target_slug'] ?? '');
            $targetHref = match ($type) {
                'question' => $targetSlug !== '' ? '/question/' . rawurlencode($targetSlug) : '',
                'answer' => $targetSlug !== '' ? '/question/' . rawurlencode($targetSlug) . '#answer-' . $targetId : '',
                'comment' => $targetSlug !== '' ? '/question/' . rawurlencode($targetSlug) . '#comment-' . $targetId : '',
                'user' => !empty($flag['target_username']) ? '/user/' . rawurlencode((string) $flag['target_username']) : '',
                default => '',
            };
            ?>
            <tr>
                <td>
                    <strong><?= h($type) ?> #<?= $targetId ?></strong><br>
                    <?php if ($targetHref !== ''): ?><a href="<?= h($targetHref) ?>"><?= h($targetTitle) ?></a><?php else: ?><?= h($targetTitle) ?><?php endif; ?>
                    <?php if ($type === 'comment' && !empty($flag['comment_body'])): ?><p class="meta"><?= h(excerpt((string) $flag['comment_body'], 90)) ?></p><?php endif; ?>
                </td>
                <td><?= h((string) $flag['reason']) ?></td>
                <td><?= h((string) $flag['username']) ?></td>
                <td class="moderation-actions">
                    <?php if (in_array($type, ['question', 'answer', 'comment'], true)): ?>
                        <a class="button button-secondary" href="/moderator/<?= h($type) ?>/<?= $targetId ?>/edit">Edit</a>
                        <form method="post" action="/moderator/<?= h($type) ?>/<?= $targetId ?>/delete">
                            <?= $csrf->field('/moderator/' . $type . '/' . $targetId . '/delete') ?>
                            <button type="submit">Delete</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($type === 'question'): ?>
                        <form method="post" action="/moderator/question/<?= $targetId ?>/close">
                            <?= $csrf->field('/moderator/question/' . $targetId . '/close') ?>
                            <button type="submit">Close</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="/moderator/flags/<?= (int) $flag['id'] ?>/resolve">
                        <?= $csrf->field('/moderator/flags/' . (int) $flag['id'] . '/resolve') ?>
                        <input type="hidden" name="status" value="resolved">
                        <button type="submit">Resolve</button>
                    </form>
                    <form method="post" action="/moderator/flags/<?= (int) $flag['id'] ?>/resolve">
                        <?= $csrf->field('/moderator/flags/' . (int) $flag['id'] . '/resolve') ?>
                        <input type="hidden" name="status" value="rejected">
                        <button type="submit">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($flags === []): ?>
            <tr><td colspan="4">No open reports.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<section class="table-wrap">
    <h2>Audit log</h2>
    <table>
        <thead><tr><th>When</th><th>Moderator</th><th>Action</th><th>Target</th></tr></thead>
        <tbody>
        <?php foreach ($auditLog as $entry): ?>
            <tr>
                <td><?= h((string) $entry['created_at']) ?></td>
                <td><?= h((string) ($entry['username'] ?? 'system')) ?></td>
                <td><?= h((string) $entry['action']) ?></td>
                <td><?= h((string) $entry['target_type']) ?> #<?= (int) $entry['target_id'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
