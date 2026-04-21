<?php declare(strict_types=1); ?>
<?php
$zone = is_string($widgetZone ?? null) ? $widgetZone : '';
$widgetsForZone = is_array($pageWidgets['zones'][$zone] ?? null) ? $pageWidgets['zones'][$zone] : [];
$columnDevices = [];
foreach ($widgetsForZone as $widgetForDevice) {
    $widgetDevices = is_array($widgetForDevice['devices'] ?? null) ? array_map('strval', $widgetForDevice['devices']) : ['desktop', 'phone'];
    foreach ($widgetDevices as $device) {
        $columnDevices[$device] = true;
    }
}
$columnDeviceClass = '';
if (empty($columnDevices['desktop'])) {
    $columnDeviceClass .= ' widgets-column-phone-only';
}
if (empty($columnDevices['phone'])) {
    $columnDeviceClass .= ' widgets-column-desktop-only';
}
$initial = static function (string $name): string {
    $name = trim($name);
    return $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : '?';
};
?>
<?php if ($widgetsForZone !== []): ?>
    <aside class="dynamic-widget-column dynamic-widget-<?= h($zone) ?><?= h($columnDeviceClass) ?>" aria-label="<?= h(t('widgets.column.label', 'Dynamic widgets')) ?>">
        <?php foreach ($widgetsForZone as $widget): ?>
            <?php
            $type = (string) ($widget['type'] ?? '');
            $items = is_array($widget['items'] ?? null) ? $widget['items'] : [];
            $widgetDevices = is_array($widget['devices'] ?? null) ? array_map('strval', $widget['devices']) : ['desktop', 'phone'];
            $widgetDeviceClass = '';
            if (!in_array('desktop', $widgetDevices, true)) {
                $widgetDeviceClass .= ' dynamic-widget-phone-only';
            }
            if (!in_array('phone', $widgetDevices, true)) {
                $widgetDeviceClass .= ' dynamic-widget-desktop-only';
            }
            ?>
            <section class="dynamic-widget dynamic-widget-type-<?= h($type) ?><?= h($widgetDeviceClass) ?>">
                <h2><?= h((string) ($widget['title'] ?? '')) ?></h2>
                <div class="dynamic-widget-body">
                    <?php if ($items === []): ?>
                        <p class="dynamic-widget-empty"><?= h(t('widgets.empty', 'Nothing to show yet.')) ?></p>
                    <?php elseif ($type === 'categories'): ?>
                        <div class="dynamic-link-list">
                            <?php foreach ($items as $item): ?>
                                <a href="/category/<?= h((string) $item['slug']) ?>">
                                    <span class="dynamic-link-title"><?= h((string) $item['name']) ?></span>
                                    <strong class="dynamic-count-badge"><?= (int) ($item['count'] ?? 0) ?></strong>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (in_array($type, ['last_registered_users', 'top_users'], true)): ?>
                        <div class="dynamic-user-list">
                            <?php foreach ($items as $item): ?>
                                <?php $username = (string) ($item['username'] ?? ''); ?>
                                <a href="/user/<?= rawurlencode($username) ?>">
                                    <span class="dynamic-avatar">
                                        <?php if (!empty($item['avatar_path'])): ?>
                                            <?= uploaded_image((string) $item['avatar_path'], '', $username . ' avatar') ?>
                                        <?php else: ?>
                                            <span><?= h($initial($username)) ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="dynamic-user-copy">
                                        <strong><?= h($username) ?></strong>
                                        <?php if ($type === 'top_users'): ?>
                                            <small>
                                                <?= (int) ($item['reputation'] ?? 0) ?> <?= h(t('widgets.reputation', 'reputation')) ?>
                                                &middot; <?= (int) ($item['activity_score'] ?? 0) ?> <?= h(t('widgets.activity', 'activity')) ?>
                                            </small>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($type === 'platform_stats'): ?>
                        <div class="dynamic-stat-grid">
                            <?php foreach ($items as $item): ?>
                                <div>
                                    <strong><?= (int) ($item['value'] ?? 0) ?></strong>
                                    <span><?= h((string) ($item['label'] ?? '')) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (in_array($type, ['accepted_answers', 'community_answers'], true)): ?>
                        <div class="dynamic-story-list">
                            <?php foreach ($items as $item): ?>
                                <a href="/question/<?= h((string) $item['question_slug']) ?>#answer-<?= (int) ($item['id'] ?? 0) ?>">
                                    <strong><?= h((string) ($item['question_title'] ?? '')) ?></strong>
                                    <span class="dynamic-story-excerpt"><?= h(excerpt((string) ($item['body'] ?? ''), 110)) ?></span>
                                    <small class="dynamic-story-meta">
                                        <span><?= h((string) ($item['username'] ?? '')) ?></span>
                                        <?php if ($type === 'community_answers'): ?>
                                            <span><?= (int) ($item['score'] ?? 0) ?> <?= h(t('widgets.score', 'score')) ?></span>
                                        <?php else: ?>
                                            <span><?= (int) ($item['votes'] ?? 0) ?> <?= h(t('widgets.votes', 'votes')) ?></span>
                                        <?php endif; ?>
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="dynamic-story-list">
                            <?php foreach ($items as $item): ?>
                                <a href="/question/<?= h((string) $item['slug']) ?>">
                                    <strong><?= h((string) ($item['title'] ?? '')) ?></strong>
                                    <small class="dynamic-story-meta">
                                        <span><?= (int) ($item['answer_count'] ?? 0) ?> <?= h(t('widgets.answers', 'answers')) ?></span>
                                        <span><?= (int) ($item['views'] ?? 0) ?> <?= h(t('widgets.views', 'views')) ?></span>
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </aside>
<?php endif; ?>
