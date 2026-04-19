<?php declare(strict_types=1); ?>
<?php
$stats = is_array($moderatorStats ?? null) ? $moderatorStats : [];
$returnTo = is_string($moderatorReturnTo ?? null) && $moderatorReturnTo !== '' ? $moderatorReturnTo : '/moderator';
$renderPagination = static function (array $pagination, string $label, string $itemLabel): void {
    if ((int) ($pagination['total_pages'] ?? 1) <= 1) {
        return;
    }

    $href = $pagination['href'] ?? null;
    if (!$href instanceof \Closure) {
        return;
    }

    $window = is_array($pagination['window'] ?? null) ? $pagination['window'] : [];
    ?>
    <nav class="pagination moderator-pagination" aria-label="<?= h($label) ?>">
        <span><?= (int) ($pagination['total_items'] ?? 0) ?> <?= h($itemLabel) ?></span>
        <?php if (!empty($pagination['previous'])): ?><a href="<?= h((string) $pagination['previous']) ?>" rel="prev"><?= h(t('pagination.previous', 'Previous')) ?></a><?php endif; ?>
        <?php if ($window !== [] && (int) $window[0] > 1): ?>
            <a href="<?= h($href(1)) ?>">1</a>
            <?php if ((int) $window[0] > 2): ?><span>...</span><?php endif; ?>
        <?php endif; ?>
        <?php foreach ($window as $pageNumber): ?>
            <?php $target = (int) $pageNumber; ?>
            <?php if ($target === (int) ($pagination['current'] ?? 1)): ?>
                <span aria-current="page"><?= $target ?></span>
            <?php else: ?>
                <a href="<?= h($href($target)) ?>"><?= $target ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($window !== [] && (int) end($window) < (int) ($pagination['total_pages'] ?? 1)): ?>
            <?php if ((int) end($window) < (int) ($pagination['total_pages'] ?? 1) - 1): ?><span>...</span><?php endif; ?>
            <?php $last = (int) $pagination['total_pages']; ?>
            <a href="<?= h($href($last)) ?>"><?= $last ?></a>
        <?php endif; ?>
        <?php if (!empty($pagination['next'])): ?><a href="<?= h((string) $pagination['next']) ?>" rel="next"><?= h(t('pagination.next', 'Next')) ?></a><?php endif; ?>
    </nav>
    <?php
};
?>
<section class="page-head moderator-head">
    <div>
        <h1><?= h(t('moderator.dashboard.title', 'Moderator tools')) ?></h1>
        <p><?= h(t('moderator.dashboard.intro', 'Review open reports, edit content, remove unsafe posts, and keep the queue moving.')) ?></p>
    </div>
    <div class="moderator-summary" aria-label="<?= h(t('moderator.dashboard.summary_label', 'Moderation summary')) ?>">
        <div>
            <strong><?= (int) ($stats['open_reports'] ?? 0) ?></strong>
            <span><?= h(t('moderator.dashboard.open_reports', 'Open reports')) ?></span>
        </div>
        <div>
            <strong><?= (int) ($stats['audit_entries'] ?? 0) ?></strong>
            <span><?= h(t('moderator.dashboard.audit_entries', 'Audit entries')) ?></span>
        </div>
        <div>
            <strong><?= (int) ($stats['report_page_size'] ?? count($flags)) ?></strong>
            <span><?= h(t('moderator.dashboard.reports_per_page', 'Reports per page')) ?></span>
        </div>
    </div>
</section>

<section class="moderator-board" aria-labelledby="moderator-reports-title">
    <div class="moderator-section-head">
        <div>
            <h2 id="moderator-reports-title"><?= h(t('moderator.reports.title', 'Open reports')) ?></h2>
            <p class="meta"><?= h(t('moderator.reports.help', 'Start with the target, inspect the report reason, then choose the least destructive action that solves it.')) ?></p>
        </div>
        <p class="meta"><?= (int) ($reportsPagination['total_items'] ?? count($flags)) ?> <?= h(t('moderator.reports.count_label', 'reports total')) ?></p>
    </div>

    <div class="moderator-report-list">
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
            <article class="moderator-report">
                <div class="moderator-report-main">
                    <div class="moderator-report-top">
                        <div>
                            <p class="moderator-label"><?= h($type) ?> #<?= $targetId ?></p>
                            <h3>
                                <?php if ($targetHref !== ''): ?>
                                    <a href="<?= h($targetHref) ?>"><?= h($targetTitle) ?></a>
                                <?php else: ?>
                                    <?= h($targetTitle) ?>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <span class="moderator-status"><?= h(t('moderator.reports.open_status', 'Open')) ?></span>
                    </div>
                    <div class="moderator-report-meta">
                        <span><?= h(t('moderator.reports.reported_by', 'Reported by')) ?> <?= h((string) $flag['username']) ?></span>
                        <span><?= h((string) $flag['created_at']) ?></span>
                    </div>
                    <p class="moderator-reason"><strong><?= h(t('moderator.reports.reason', 'Reason')) ?>:</strong> <?= h((string) $flag['reason']) ?></p>
                    <?php if ($type === 'comment' && !empty($flag['comment_body'])): ?>
                        <p class="meta"><?= h(excerpt((string) $flag['comment_body'], 140)) ?></p>
                    <?php endif; ?>
                </div>

                <div class="moderator-action-panel" aria-label="<?= h(t('moderator.reports.actions_label', 'Report actions')) ?>">
                    <?php if (in_array($type, ['question', 'answer', 'comment'], true)): ?>
                        <div class="moderator-action-group">
                            <span><?= h(t('moderator.reports.content_actions', 'Content')) ?></span>
                            <div class="moderation-actions">
                                <a class="button button-secondary" href="/moderator/<?= h($type) ?>/<?= $targetId ?>/edit"><?= h(t('moderator.actions.edit', 'Edit')) ?></a>
                                <?php if ($type === 'question'): ?>
                                    <form method="post" action="/moderator/question/<?= $targetId ?>/close">
                                        <?= $csrf->field('/moderator/question/' . $targetId . '/close') ?>
                                        <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                                        <button type="submit" class="button-secondary"><?= h(t('moderator.actions.close', 'Close')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="moderator-action-group">
                        <span><?= h(t('moderator.reports.report_actions', 'Report')) ?></span>
                        <div class="moderation-actions">
                            <form method="post" action="/moderator/flags/<?= (int) $flag['id'] ?>/resolve">
                                <?= $csrf->field('/moderator/flags/' . (int) $flag['id'] . '/resolve') ?>
                                <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                                <input type="hidden" name="status" value="resolved">
                                <button type="submit"><?= h(t('moderator.actions.resolve', 'Resolve')) ?></button>
                            </form>
                            <form method="post" action="/moderator/flags/<?= (int) $flag['id'] ?>/resolve">
                                <?= $csrf->field('/moderator/flags/' . (int) $flag['id'] . '/resolve') ?>
                                <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit" class="button-secondary"><?= h(t('moderator.actions.reject', 'Reject')) ?></button>
                            </form>
                        </div>
                    </div>

                    <?php if (in_array($type, ['question', 'answer', 'comment'], true)): ?>
                        <div class="moderator-action-group moderator-danger-zone">
                            <span><?= h(t('moderator.reports.danger_actions', 'Removal')) ?></span>
                            <form method="post" action="/moderator/<?= h($type) ?>/<?= $targetId ?>/delete">
                                <?= $csrf->field('/moderator/' . $type . '/' . $targetId . '/delete') ?>
                                <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                                <button type="submit" class="button-danger"><?= h(t('moderator.actions.delete', 'Delete')) ?></button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if ($flags === []): ?>
            <p class="admin-empty"><?= h(t('moderator.reports.empty', 'No open reports.')) ?></p>
        <?php endif; ?>
    </div>
    <?php $renderPagination(is_array($reportsPagination ?? null) ? $reportsPagination : [], t('moderator.reports.pagination_label', 'Report pages'), t('moderator.reports.pagination_items', 'reports')); ?>
</section>

<section class="moderator-board" aria-labelledby="moderator-audit-title">
    <div class="moderator-section-head">
        <div>
            <h2 id="moderator-audit-title"><?= h(t('moderator.audit.title', 'Audit log')) ?></h2>
            <p class="meta"><?= h(t('moderator.audit.help', 'Recent moderation changes, who made them, and which target was affected.')) ?></p>
        </div>
        <p class="meta"><?= (int) ($auditPagination['total_items'] ?? count($auditLog)) ?> <?= h(t('moderator.audit.count_label', 'entries total')) ?></p>
    </div>

    <div class="moderator-audit-list">
        <?php foreach ($auditLog as $entry): ?>
            <?php
            $entryCreatedAt = (string) $entry['created_at'];
            $entryTimestamp = strtotime($entryCreatedAt);
            $entryIsoDate = $entryTimestamp === false ? $entryCreatedAt : date(DATE_ATOM, $entryTimestamp);
            ?>
            <article class="moderator-audit-item">
                <time datetime="<?= h($entryIsoDate) ?>"><?= h($entryCreatedAt) ?></time>
                <div>
                    <strong><?= h((string) $entry['action']) ?></strong>
                    <span><?= h((string) ($entry['username'] ?? 'system')) ?></span>
                </div>
                <span class="moderator-target"><?= h((string) $entry['target_type']) ?> #<?= (int) $entry['target_id'] ?></span>
            </article>
        <?php endforeach; ?>
        <?php if ($auditLog === []): ?>
            <p class="admin-empty"><?= h(t('moderator.audit.empty', 'No audit entries yet.')) ?></p>
        <?php endif; ?>
    </div>
    <?php $renderPagination(is_array($auditPagination ?? null) ? $auditPagination : [], t('moderator.audit.pagination_label', 'Audit pages'), t('moderator.audit.pagination_items', 'entries')); ?>
</section>
