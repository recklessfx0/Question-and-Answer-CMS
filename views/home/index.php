<?php declare(strict_types=1); ?>
<?php
require_once BASE_PATH . '/views/partials/icon.php';
$pageWidgets = is_array($pageWidgets ?? null) ? $pageWidgets : [];
$hasDynamicWidgets = !empty($pageWidgets['has_widgets']);
$widgetDevices = is_array($pageWidgets['devices'] ?? null) ? array_map('strval', $pageWidgets['devices']) : ['desktop', 'phone'];
$widgetLayoutClass = '';
if (!in_array('desktop', $widgetDevices, true)) {
    $widgetLayoutClass .= ' widgets-hide-desktop';
}
if (!in_array('phone', $widgetDevices, true)) {
    $widgetLayoutClass .= ' widgets-hide-phone';
}
$widgetZone = '';
$feedTopics = [];
if (!$hasDynamicWidgets && empty($query) && empty($category) && empty($tagInfo)) {
    foreach ($questions as $item) {
        if (!empty($item['category_slug'])) {
            $feedTopics[(string) $item['category_slug']] = (string) $item['category_name'];
        }
    }
}
?>
<section class="page-head">
    <?php require BASE_PATH . '/views/partials/guest-welcome.php'; ?>
    <div class="feed-intro<?= empty($query) && empty($category) && empty($tagInfo) ? ' feed-intro-illustrated' : '' ?>">
        <div>
            <h1><?= h($heading ?? $title ?? 'Latest Questions') ?></h1>
            <p><?= h($metaDescription ?? 'Browse community questions.') ?></p>
        </div>
        <?php if (empty($query) && empty($category) && empty($tagInfo)): ?>
            <svg class="feed-illustration" viewBox="0 0 216 132" width="216" height="132" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path class="illustration-soft" d="m14 95 91-48 97 48-94 34z"/>
                <path class="illustration-panel" d="m41 40 60-32 41 22v54l-42 21-59-30z"/>
                <path d="m41 40 59 31 42-41m-42 41v34M63 28l58 31M55 55l30 16m-30-7 21 11"/>
                <path class="illustration-panel" d="m121 68 37-20 32 17v32l-34 18-35-18z"/>
                <path d="m121 68 35 19 34-22m-34 22v28m9-21 16-9"/>
            </svg>
        <?php endif; ?>
    </div>
    <?php if (!empty($category) && is_array($category)): ?>
        <div class="hub-panel" aria-label="Category hub">
            <p class="meta"><?= h((string) (($category['intro'] ?? '') ?: 'Topic hub for ' . (string) $category['name'] . '. Browse subcategories, related tags, current questions, and recent answers.')) ?></p>
            <?php if (!empty($subcategories) && is_array($subcategories)): ?>
                <div class="hub-links" aria-label="Subcategories">
                    <?php foreach ($subcategories as $subcategory): ?>
                        <a href="<?= h(localized_path('/category/' . (string) $subcategory['slug'])) ?>"><?= h((string) $subcategory['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($relatedTags) && is_array($relatedTags)): ?>
                <div class="hub-links" aria-label="Popular tags in this category">
                    <?php foreach ($relatedTags as $relatedTag): ?>
                        <a href="<?= h(localized_path('/tag/' . (string) $relatedTag['slug'])) ?>">#<?= h((string) $relatedTag['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($tagInfo) && is_array($tagInfo)): ?>
        <div class="hub-panel" aria-label="Tag hub">
            <p class="meta"><?= h((string) (($tagInfo['intro'] ?? '') ?: ((int) ($tagInfo['question_count'] ?? 0) . ' public questions use #' . (string) $tagInfo['name'] . '. Use related tags to narrow the topic.'))) ?></p>
            <?php if (!empty($relatedTags) && is_array($relatedTags)): ?>
                <div class="hub-links" aria-label="Related tags">
                    <?php foreach ($relatedTags as $relatedTag): ?>
                        <a href="<?= h(localized_path('/tag/' . (string) $relatedTag['slug'])) ?>">#<?= h((string) $relatedTag['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <nav class="landing-tabs" aria-label="Question navigation">
        <?php $feedPath = rtrim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/'); ?>
        <?php foreach (['/questions' => 'Questions', '/activity' => 'All activity', '/unanswered' => 'Unanswered', '/tags' => 'Tags', '/users' => 'Members'] as $path => $label): ?>
            <?php $href = localized_path($path); ?>
            <a href="<?= h($href) ?>" <?= in_array($path, ['/tags', '/users'], true) ? 'class="feed-tab-directory"' : '' ?> <?= $feedPath === rtrim($href, '/') || ($path === '/questions' && $feedPath === rtrim(localized_path('/'), '/')) ? 'aria-current="page"' : '' ?>><?= h($label) ?></a>
        <?php endforeach; ?>
    </nav>
</section>

<?php if ($hasDynamicWidgets): ?>
    <div class="content-with-widgets landing-with-widgets<?= h($widgetLayoutClass) ?>">
        <?php $widgetZone = 'left'; require BASE_PATH . '/views/partials/dynamic-widgets.php'; ?>
        <div class="dynamic-widget-main">
<?php elseif ($feedTopics !== []): ?>
    <div class="feed-layout">
<?php endif; ?>

<section class="question-list" aria-label="Questions">
    <?php if ($questions === []): ?>
        <div class="empty-state">
            <span class="topic-mark"><?= ui_icon('topics') ?></span>
            <h2>No questions found</h2>
            <?php if (!empty($query)): ?>
                <p>Try a shorter search or browse all questions.</p>
                <a class="button button-secondary" href="<?= h(localized_path('/questions')) ?>">Browse questions</a>
            <?php else: ?>
                <a class="button button-secondary" href="<?= h(localized_path('/ask')) ?>">Ask a question</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php foreach ($questions as $question): ?>
        <?php $activityUsers = is_array($question['activity_users'] ?? null) ? array_slice($question['activity_users'], 0, 9) : []; ?>
        <?php $latestMedia = is_array($question['latest_media'] ?? null) ? array_slice($question['latest_media'], 0, 4) : []; ?>
        <?php $activityDays = is_array($question['activity_days'] ?? null) ? $question['activity_days'] : []; ?>
        <?php $totalVotes = (int) ($question['total_vote_count'] ?? $question['votes'] ?? 0); ?>
        <?php $maxActivity = max([1, ...array_values(array_map('intval', $activityDays))]); ?>
        <?php $activityTotal = array_sum(array_map('intval', $activityDays)); ?>
        <article class="question-row question-card <?= $activityUsers !== [] ? 'question-card-has-activity' : '' ?> <?= $latestMedia !== [] ? 'question-card-has-media' : '' ?>">
            <div class="question-side">
                <div class="question-stats" aria-label="Question stats">
                    <div class="question-stat">
                        <strong><?= $totalVotes ?></strong>
                        <span><?= $totalVotes === 1 ? 'vote' : 'votes' ?></span>
                    </div>
                    <div class="question-stat">
                        <strong><?= (int) ($question['answer_count'] ?? 0) ?></strong>
                        <span><?= (int) ($question['answer_count'] ?? 0) === 1 ? 'answer' : 'answers' ?></span>
                    </div>
                    <div class="question-views"><strong><?= (int) ($question['views'] ?? 0) ?></strong> views</div>
                </div>
            </div>
            <div class="question-summary">
                <h2><a href="<?= h(localized_path('/question/' . (string) $question['slug'])) ?>"><?= h((string) $question['title']) ?></a></h2>
                <p><?= h(excerpt((string) $question['body'], 180)) ?></p>
                <?php if (!empty($question['card_tags']) && is_array($question['card_tags'])): ?>
                    <?php $cardTags = array_slice($question['card_tags'], 0, 8); ?>
                    <div class="question-card-tags" aria-label="Question tags">
                        <?php foreach ($cardTags as $tag): ?>
                            <a href="<?= h(localized_path('/tag/' . (string) $tag['slug'])) ?>">#<?= h((string) $tag['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="question-card-footer">
                    <div class="question-card-meta">
                        <?php if (!empty($question['category_slug'])): ?>
                            <p class="meta">Category <a href="<?= h(localized_path('/category/' . (string) $question['category_slug'])) ?>"><?= h((string) $question['category_name']) ?></a></p>
                        <?php endif; ?>
                        <p class="meta">Asked by <a href="<?= h(localized_path('/user/' . rawurlencode((string) $question['username']))) ?>"><?= h((string) $question['username']) ?></a> &middot; <?= h((string) $question['created_at']) ?></p>
                    </div>
                    <?php if ($latestMedia !== []): ?>
                        <div class="card-media-strip" aria-label="Latest uploaded content">
                            <?php foreach ($latestMedia as $path): ?>
                                <?php $extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION)); ?>
                                <a href="<?= h((string) $path) ?>" class="media-link card-media-thumb" data-media-popup="1">
                                    <?php if (in_array($extension, ['mp4', 'webm'], true)): ?>
                                        <video muted preload="metadata" aria-label="Uploaded video for <?= h((string) $question['title']) ?>"><source src="<?= h((string) $path) ?>" type="<?= $extension === 'webm' ? 'video/webm' : 'video/mp4' ?>"></video>
                                    <?php else: ?>
                                        <?= uploaded_image((string) $path, '', 'Uploaded media for ' . (string) $question['title']) ?>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($activityUsers !== []): ?>
                <aside class="question-activity-panel" aria-label="Latest question activity">
                    <div class="activity-grid" aria-label="Latest activity users">
                        <?php foreach ($activityUsers as $activityUser): ?>
                            <a class="activity-icon" href="<?= h(localized_path('/user/' . rawurlencode((string) $activityUser['username']))) ?>" title="<?= h((string) $activityUser['username']) ?>">
                                <?php if (!empty($activityUser['avatar_path'])): ?>
                                    <?= uploaded_image((string) $activityUser['avatar_path'], '', (string) $activityUser['username'] . ' avatar') ?>
                                <?php else: ?>
                                    <span><?= h(strtoupper(substr((string) $activityUser['username'], 0, 1))) ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="activity-spark-wrap" aria-label="Activity in the past 31 days">
                        <div class="activity-spark">
                            <?php
                            for ($day = 30; $day >= 0; $day--) {
                                $date = date('Y-m-d', strtotime('-' . $day . ' days'));
                                $count = (int) ($activityDays[$date] ?? 0);
                                $level = $count > 0 ? max(1, min(10, (int) ceil(($count / $maxActivity) * 10))) : 0;
                                $state = $count > 0 ? 'is-active' : 'is-empty';
                                echo '<span class="' . $state . ' activity-level-' . $level . '" title="' . h($date . ': ' . $count) . '"></span>';
                            }
                            ?>
                        </div>
                        <span class="activity-spark-title"><?= $activityTotal > 0 ? (int) $activityTotal . ' events in 31 days' : 'No recent activity' ?></span>
                    </div>
                </aside>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php if ($feedTopics !== []): ?>
        <aside class="feed-topics" aria-labelledby="feed-topics-heading">
            <h2 id="feed-topics-heading">Topics in this feed</h2>
            <ul>
                <?php foreach ($feedTopics as $slug => $name): ?>
                    <li><a href="<?= h(localized_path('/category/' . $slug)) ?>"><?= ui_icon('topics') ?><?= h($name) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <a class="feed-topics-directory" href="<?= h(localized_path('/tags')) ?>">Browse all tags</a>
        </aside>
    </div>
<?php endif; ?>

<?php if (!empty($pagination) && is_array($pagination) && (int) ($pagination['total_pages'] ?? 1) > 1): ?>
    <nav class="pagination" aria-label="Question pages">
        <span><?= (int) ($pagination['total_items'] ?? 0) ?> questions</span>
        <?php if (!empty($pagination['previous'])): ?><a href="<?= h((string) $pagination['previous']) ?>" rel="prev">Previous</a><?php endif; ?>
        <?php $window = is_array($pagination['window'] ?? null) ? $pagination['window'] : []; ?>
        <?php if ($window !== [] && (int) $window[0] > 1): ?>
            <a href="<?= h(localized_path((string) ($pagination['query_mode'] ?? false ? $pagination['base'] : rtrim((string) $pagination['base'], '/')))) ?>">1</a>
            <?php if ((int) $window[0] > 2): ?><span>...</span><?php endif; ?>
        <?php endif; ?>
        <?php foreach ($window as $pageNumber): ?>
            <?php $target = (int) $pageNumber; ?>
            <?php
            $href = !empty($pagination['query_mode'])
                ? (string) $pagination['base'] . ($target > 1 ? '&page=' . $target : '')
                : ($target > 1 ? rtrim((string) $pagination['base'], '/') . '/page/' . $target : rtrim((string) $pagination['base'], '/'));
            ?>
            <?php if ($target === (int) ($pagination['current'] ?? 1)): ?>
                <span aria-current="page"><?= $target ?></span>
            <?php else: ?>
                <a href="<?= h(localized_path($href)) ?>"><?= $target ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($window !== [] && (int) end($window) < (int) ($pagination['total_pages'] ?? 1)): ?>
            <?php if ((int) end($window) < (int) ($pagination['total_pages'] ?? 1) - 1): ?><span>...</span><?php endif; ?>
            <?php $last = (int) $pagination['total_pages']; ?>
            <a href="<?= h(localized_path(!empty($pagination['query_mode']) ? (string) $pagination['base'] . '&page=' . $last : rtrim((string) $pagination['base'], '/') . '/page/' . $last)) ?>"><?= $last ?></a>
        <?php endif; ?>
        <?php if (!empty($pagination['next'])): ?><a href="<?= h((string) $pagination['next']) ?>" rel="next">Next</a><?php endif; ?>
    </nav>
<?php endif; ?>

<?php if ($hasDynamicWidgets): ?>
        </div>
        <?php $widgetZone = 'right'; require BASE_PATH . '/views/partials/dynamic-widgets.php'; ?>
    </div>
<?php endif; ?>
