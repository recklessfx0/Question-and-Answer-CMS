<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<?php
$currentPage = max(1, (int) ($adminPage ?? 1));
$returnTo = '/admin/reports' . ($currentPage > 1 ? '?page=' . $currentPage : '');
?>
<section class="page-head admin-page-head">
    <div>
        <h1>Reports</h1>
        <p class="meta">Review open reports for questions, answers, comments, and users.</p>
    </div>
</section>

<section class="admin-management">
    <div class="admin-section-head">
        <h2>Open Reports</h2>
        <p class="meta"><?= (int) ($adminPagination['total_items'] ?? count($reports)) ?> reports total</p>
    </div>
    <div class="admin-record-list">
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
            <article class="admin-record">
                <div class="admin-record-summary">
                    <div>
                        <h3><?= h($type) ?> #<?= $targetId ?></h3>
                        <p class="meta">
                            Reported by <?= h((string) $report['username']) ?> · <?= h((string) $report['created_at']) ?>
                        </p>
                    </div>
                    <div class="admin-badges"><span>Open</span></div>
                </div>
                <p class="admin-record-copy">
                    <?php if ($targetHref !== ''): ?><a href="<?= h($targetHref) ?>"><?= h($targetTitle) ?></a><?php else: ?><?= h($targetTitle) ?><?php endif; ?>
                </p>
                <?php if ($type === 'comment' && !empty($report['comment_body'])): ?>
                    <p class="meta"><?= h(excerpt((string) $report['comment_body'], 120)) ?></p>
                <?php endif; ?>
                <p class="admin-report-reason"><strong>Reason:</strong> <?= h((string) $report['reason']) ?></p>
                <div class="admin-action-row">
                    <?php if (in_array($type, ['question', 'answer', 'comment'], true)): ?>
                        <a class="button button-secondary" href="/moderator/<?= h($type) ?>/<?= $targetId ?>/edit">Edit Content</a>
                        <form method="post" action="/admin/reports/<?= h($type) ?>/<?= $targetId ?>/delete">
                            <?= $csrf->field('/admin/reports/' . $type . '/' . $targetId . '/delete') ?>
                            <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                            <button type="submit" class="button-danger">Delete Content</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($type === 'question'): ?>
                        <form method="post" action="/admin/reports/question/<?= $targetId ?>/close">
                            <?= $csrf->field('/admin/reports/question/' . $targetId . '/close') ?>
                            <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                            <button type="submit" class="button-secondary">Close Question</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="/admin/reports/<?= (int) $report['id'] ?>/resolve">
                        <?= $csrf->field('/admin/reports/' . (int) $report['id'] . '/resolve') ?>
                        <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                        <input type="hidden" name="status" value="resolved">
                        <button type="submit">Resolve</button>
                    </form>
                    <form method="post" action="/admin/reports/<?= (int) $report['id'] ?>/resolve">
                        <?= $csrf->field('/admin/reports/' . (int) $report['id'] . '/resolve') ?>
                        <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                        <input type="hidden" name="status" value="rejected">
                        <button type="submit" class="button-secondary">Reject</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if ($reports === []): ?>
            <p class="admin-empty">No open reports.</p>
        <?php endif; ?>
    </div>
    <?php require BASE_PATH . '/views/admin/_pagination.php'; ?>
</section>
