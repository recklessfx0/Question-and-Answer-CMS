<?php declare(strict_types=1); ?>
<section class="page-head">
    <h1><?= h($title ?? 'Latest Questions') ?></h1>
    <p><?= h($metaDescription ?? 'Browse community questions.') ?></p>
    <?php if (!empty($category) && is_array($category)): ?>
        <div class="hub-panel" aria-label="Category hub">
            <p class="meta"><?= h((string) (($category['intro'] ?? '') ?: 'Topic hub for ' . (string) $category['name'] . '. Browse subcategories, related tags, current questions, and recent answers.')) ?></p>
            <?php if (!empty($subcategories) && is_array($subcategories)): ?>
                <div class="hub-links" aria-label="Subcategories">
                    <?php foreach ($subcategories as $subcategory): ?>
                        <a href="/category/<?= h((string) $subcategory['slug']) ?>"><?= h((string) $subcategory['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($relatedTags) && is_array($relatedTags)): ?>
                <div class="hub-links" aria-label="Popular tags in this category">
                    <?php foreach ($relatedTags as $relatedTag): ?>
                        <a href="/tag/<?= h((string) $relatedTag['slug']) ?>">#<?= h((string) $relatedTag['name']) ?></a>
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
                        <a href="/tag/<?= h((string) $relatedTag['slug']) ?>">#<?= h((string) $relatedTag['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <nav class="landing-tabs" aria-label="Question navigation">
        <a href="/activity">All activity</a>
        <a href="/questions">Questions</a>
        <a href="/unanswered">Unanswered</a>
        <a href="/tags">Tags</a>
        <a href="/users">Users</a>
        <a href="/ask">Ask a Question</a>
    </nav>
</section>

<section class="question-list" aria-label="Questions">
    <?php if ($questions === []): ?>
        <p>No questions found.</p>
    <?php endif; ?>
    <?php foreach ($questions as $question): ?>
        <?php $activityUsers = is_array($question['activity_users'] ?? null) ? array_slice($question['activity_users'], 0, 9) : []; ?>
        <?php $latestMedia = is_array($question['latest_media'] ?? null) ? array_slice($question['latest_media'], 0, 4) : []; ?>
        <?php $activityDays = is_array($question['activity_days'] ?? null) ? $question['activity_days'] : []; ?>
        <?php $maxActivity = max([1, ...array_values(array_map('intval', $activityDays))]); ?>
        <?php $activityTotal = array_sum(array_map('intval', $activityDays)); ?>
        <article class="question-row question-card <?= $activityUsers !== [] ? 'question-card-has-activity' : '' ?> <?= $latestMedia !== [] ? 'question-card-has-media' : '' ?>">
            <div class="question-side">
                <div class="question-stats" aria-label="Question stats">
                    <div class="question-stat">
                        <strong><?= (int) ($question['votes'] ?? 0) ?></strong>
                        <span><?= (int) ($question['votes'] ?? 0) === 1 ? 'vote' : 'votes' ?></span>
                    </div>
                    <div class="question-stat">
                        <strong><?= (int) ($question['answer_count'] ?? 0) ?></strong>
                        <span><?= (int) ($question['answer_count'] ?? 0) === 1 ? 'answer' : 'answers' ?></span>
                    </div>
                    <div class="question-views"><strong><?= (int) ($question['views'] ?? 0) ?></strong> views</div>
                </div>
            </div>
            <div class="question-summary">
                <h2><a href="/question/<?= h((string) $question['slug']) ?>"><?= h((string) $question['title']) ?></a></h2>
                <p><?= h(excerpt((string) $question['body'], 180)) ?></p>
                <?php if (!empty($question['card_tags']) && is_array($question['card_tags'])): ?>
                    <?php $cardTags = array_slice($question['card_tags'], 0, 8); ?>
                    <div class="question-card-tags" aria-label="Question tags">
                        <?php foreach ($cardTags as $tag): ?>
                            <a href="/tag/<?= h((string) $tag['slug']) ?>">#<?= h((string) $tag['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="question-card-footer">
                    <div class="question-card-meta">
                        <?php if (!empty($question['category_slug'])): ?>
                            <p class="meta">Category <a href="/category/<?= h((string) $question['category_slug']) ?>"><?= h((string) $question['category_name']) ?></a></p>
                        <?php endif; ?>
                        <p class="meta">Asked by <a href="/user/<?= h((string) $question['username']) ?>"><?= h((string) $question['username']) ?></a> &middot; <?= h((string) $question['created_at']) ?></p>
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
                            <a class="activity-icon" href="/user/<?= h((string) $activityUser['username']) ?>" title="<?= h((string) $activityUser['username']) ?>">
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
                            <?php for ($day = 30; $day >= 0; $day--): ?>
                                <?php $date = date('Y-m-d', strtotime('-' . $day . ' days')); ?>
                                <?php $count = (int) ($activityDays[$date] ?? 0); ?>
                                <?php $activityLevel = $count > 0 ? max(1, min(10, (int) ceil(($count / $maxActivity) * 10))) : 0; ?>
                                <span class="<?= $count > 0 ? 'is-active' : 'is-empty' ?> activity-level-<?= $activityLevel ?>" title="<?= h($date . ': ' . $count) ?>"></span>
                            <?php endfor; ?>
                        </div>
                        <span class="activity-spark-title"><?= $activityTotal > 0 ? (int) $activityTotal . ' events in 31 days' : 'No recent activity' ?></span>
                    </div>
                </aside>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

<?php if (!empty($pagination) && is_array($pagination) && (int) ($pagination['total_pages'] ?? 1) > 1): ?>
    <nav class="pagination" aria-label="Question pages">
        <span><?= (int) ($pagination['total_items'] ?? 0) ?> questions</span>
        <?php if (!empty($pagination['previous'])): ?><a href="<?= h((string) $pagination['previous']) ?>" rel="prev">Previous</a><?php endif; ?>
        <?php $window = is_array($pagination['window'] ?? null) ? $pagination['window'] : []; ?>
        <?php if ($window !== [] && (int) $window[0] > 1): ?>
            <a href="<?= h((string) ($pagination['query_mode'] ?? false ? $pagination['base'] : rtrim((string) $pagination['base'], '/'))) ?>">1</a>
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
                <a href="<?= h($href) ?>"><?= $target ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($window !== [] && (int) end($window) < (int) ($pagination['total_pages'] ?? 1)): ?>
            <?php if ((int) end($window) < (int) ($pagination['total_pages'] ?? 1) - 1): ?><span>...</span><?php endif; ?>
            <?php $last = (int) $pagination['total_pages']; ?>
            <a href="<?= h(!empty($pagination['query_mode']) ? (string) $pagination['base'] . '&page=' . $last : rtrim((string) $pagination['base'], '/') . '/page/' . $last) ?>"><?= $last ?></a>
        <?php endif; ?>
        <?php if (!empty($pagination['next'])): ?><a href="<?= h((string) $pagination['next']) ?>" rel="next">Next</a><?php endif; ?>
    </nav>
<?php endif; ?>
