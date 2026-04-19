<?php declare(strict_types=1); ?>
<?php $unreadTotal = (int) ($notificationUnreadTotal ?? 0); ?>
<section class="page-head notifications-head">
    <div>
        <h1><?= h(t('notifications.title', 'Notifications')) ?></h1>
        <p><?= h(t('notifications.summary', ':total total, :unread unread', ['total' => (int) ($notificationTotal ?? count($notifications)), 'unread' => $unreadTotal])) ?></p>
    </div>
    <?php if ($unreadTotal > 0): ?>
        <form method="post" action="/notifications/read">
            <?= $csrf->field('/notifications/read') ?>
            <button type="submit"><?= h(t('notifications.mark_all_read', 'Mark All Read')) ?></button>
        </form>
    <?php endif; ?>
</section>

<section class="notifications-list" aria-label="<?= h(t('notifications.list_label', 'Notification List')) ?>">
    <?php if ($notifications === []): ?>
        <div class="notifications-empty">
            <h2><?= h(t('notifications.empty_title', 'No Notifications Yet')) ?></h2>
            <p><?= h(t('notifications.empty_body', 'Replies, mentions, votes, and account updates will appear here.')) ?></p>
        </div>
    <?php endif; ?>
    <?php foreach ($notifications as $notification): ?>
        <?php
        $payload = is_array($notification['payload_data'] ?? null) ? $notification['payload_data'] : ['message' => t('notifications.default_message', 'You have an update.'), 'url' => ''];
        $isUnread = $notification['read_at'] === null;
        $url = safe_internal_path((string) ($payload['url'] ?? ''));
        ?>
        <article class="notification-item <?= $isUnread ? 'is-unread' : 'is-read' ?>">
            <div class="notification-status" aria-label="<?= h($isUnread ? t('notifications.status_unread', 'Unread') : t('notifications.status_read', 'Read')) ?>">
                <span><?= h($isUnread ? t('notifications.badge_new', 'New') : t('notifications.badge_read', 'Read')) ?></span>
            </div>
            <div class="notification-body">
                <div class="notification-title-row">
                    <h2><?= h((string) $notification['type']) ?></h2>
                    <time datetime="<?= h(date(DATE_ATOM, strtotime((string) $notification['created_at']) ?: time())) ?>"><?= h((string) $notification['created_at']) ?></time>
                </div>
                <p><?= h((string) ($payload['message'] ?? t('notifications.default_message', 'You have an update.'))) ?></p>
            </div>
            <?php if ($url !== ''): ?>
                <a class="button button-secondary notification-open" href="<?= h($url) ?>"><?= h(t('notifications.open', 'Open')) ?></a>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

<?php if (!empty($pagination) && is_array($pagination) && (int) ($pagination['total_pages'] ?? 1) > 1): ?>
    <nav class="pagination" aria-label="<?= h(t('notifications.pagination_label', 'Notification Pages')) ?>">
        <span><?= (int) ($pagination['total_items'] ?? 0) ?> <?= h(t('notifications.count_label', 'notifications')) ?></span>
        <?php if (!empty($pagination['previous'])): ?><a href="<?= h((string) $pagination['previous']) ?>" rel="prev"><?= h(t('pagination.previous', 'Previous')) ?></a><?php endif; ?>
        <?php $window = is_array($pagination['window'] ?? null) ? $pagination['window'] : []; ?>
        <?php if ($window !== [] && (int) $window[0] > 1): ?>
            <a href="/notifications">1</a>
            <?php if ((int) $window[0] > 2): ?><span>…</span><?php endif; ?>
        <?php endif; ?>
        <?php foreach ($window as $pageNumber): ?>
            <?php $target = (int) $pageNumber; ?>
            <?php $href = $target > 1 ? '/notifications?page=' . $target : '/notifications'; ?>
            <?php if ($target === (int) ($pagination['current'] ?? 1)): ?>
                <span aria-current="page"><?= $target ?></span>
            <?php else: ?>
                <a href="<?= h($href) ?>"><?= $target ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($window !== [] && (int) end($window) < (int) ($pagination['total_pages'] ?? 1)): ?>
            <?php if ((int) end($window) < (int) ($pagination['total_pages'] ?? 1) - 1): ?><span>…</span><?php endif; ?>
            <?php $last = (int) $pagination['total_pages']; ?>
            <a href="<?= h('/notifications?page=' . $last) ?>"><?= $last ?></a>
        <?php endif; ?>
        <?php if (!empty($pagination['next'])): ?><a href="<?= h((string) $pagination['next']) ?>" rel="next"><?= h(t('pagination.next', 'Next')) ?></a><?php endif; ?>
    </nav>
<?php endif; ?>
