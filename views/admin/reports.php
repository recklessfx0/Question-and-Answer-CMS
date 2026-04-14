<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<section class="page-head">
    <h1>Reports</h1>
    <p class="meta">Review open reports for questions, answers, comments, and users.</p>
</section>
<section class="table-wrap">
    <table>
        <thead><tr><th>Target</th><th>Reason</th><th>Reported by</th><th>When</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($reports as $report): ?>
            <?php
            $type = (string) $report['post_type'];
            $targetId = (int) $report['post_id'];
            $targetTitle = (string) ($report['target_title'] ?: '#' . $targetId);
            $targetSlug = (string) ($report['target_slug'] ?? '');
            $targetHref = match ($type) {
                'question' => $targetSlug !== '' ? '/question/' . rawurlencode($targetSlug) : '',
                'answer' => $targetSlug !== '' ? '/question/' . rawurlencode($targetSlug) . '#answer-' . $targetId : '',
                'comment' => $targetSlug !== '' ? '/question/' . rawurlencode($targetSlug) . '#comment-' . $targetId : '',
                'user' => !empty($report['target_username']) ? '/user/' . rawurlencode((string) $report['target_username']) : '',
                default => '',
            };
            ?>
            <tr>
                <td>
                    <strong><?= h($type) ?> #<?= $targetId ?></strong><br>
                    <?php if ($targetHref !== ''): ?><a href="<?= h($targetHref) ?>"><?= h($targetTitle) ?></a><?php else: ?><?= h($targetTitle) ?><?php endif; ?>
                    <?php if ($type === 'comment' && !empty($report['comment_body'])): ?><p class="meta"><?= h(excerpt((string) $report['comment_body'], 90)) ?></p><?php endif; ?>
                </td>
                <td><?= h((string) $report['reason']) ?></td>
                <td><?= h((string) $report['username']) ?></td>
                <td><?= h((string) $report['created_at']) ?></td>
                <td class="moderation-actions">
                    <?php if (in_array($type, ['question', 'answer', 'comment'], true)): ?>
                        <a class="button button-secondary" href="/moderator/<?= h($type) ?>/<?= $targetId ?>/edit">Edit</a>
                        <form method="post" action="/admin/reports/<?= h($type) ?>/<?= $targetId ?>/delete">
                            <?= $csrf->field('/admin/reports/' . $type . '/' . $targetId . '/delete') ?>
                            <button type="submit">Delete</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($type === 'question'): ?>
                        <form method="post" action="/admin/reports/question/<?= $targetId ?>/close">
                            <?= $csrf->field('/admin/reports/question/' . $targetId . '/close') ?>
                            <button type="submit">Close</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="/admin/reports/<?= (int) $report['id'] ?>/resolve">
                        <?= $csrf->field('/admin/reports/' . (int) $report['id'] . '/resolve') ?>
                        <input type="hidden" name="status" value="resolved">
                        <button type="submit">Resolve</button>
                    </form>
                    <form method="post" action="/admin/reports/<?= (int) $report['id'] ?>/resolve">
                        <?= $csrf->field('/admin/reports/' . (int) $report['id'] . '/resolve') ?>
                        <input type="hidden" name="status" value="rejected">
                        <button type="submit">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($reports === []): ?>
            <tr><td colspan="5">No open reports.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>
