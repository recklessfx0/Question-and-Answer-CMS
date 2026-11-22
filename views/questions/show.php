<?php

declare(strict_types=1);

$moderationSummary = static function (string $label): void {
    ?>
    <summary aria-label="<?= h($label) ?>" title="<?= h($label) ?>">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
            <circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>
        </svg>
    </summary>
    <?php
};

$viewerVotes ??= [];
$renderVote = static function (string $type, int $id, int $votes, string $label) use ($csrf, $authUser, $viewerVotes): void {
    $action = '/post/' . $type . '/' . $id . '/vote';
    $current = $viewerVotes[$type . ':' . $id] ?? 0;
    $signedIn = is_array($authUser);
    $controls = [1 => ['Upvote', 'm6 14 6-6 6 6'], -1 => ['Downvote', 'm6 10 6 6 6-6']];
    ?>
    <div class="vote-box <?= $type === 'comment' ? 'vote-box-compact' : '' ?>" role="group" aria-label="<?= h('Votes for ' . $label) ?>"<?= $signedIn ? ' data-vote-box data-post-type="' . h($type) . '" data-post-id="' . $id . '"' : '' ?>>
        <?php foreach ($controls as $value => [$verb, $path]): ?>
            <?php if ($value === -1): ?><strong data-vote-count aria-live="polite"><?= $votes ?></strong><?php endif; ?>
            <?php $active = $current === $value; ?>
            <?php if ($signedIn): ?>
                <form method="post" action="<?= h(localized_path($action)) ?>" data-async-vote-form>
                    <?= $csrf->field($action) ?>
                    <input type="hidden" name="value" value="<?= $value ?>">
                    <button type="submit" class="vote-button <?= $value === 1 ? 'vote-up' : 'vote-down' ?><?= $active ? ' is-active' : '' ?>" aria-label="<?= h($verb . ' ' . $label) ?>" aria-pressed="<?= $active ? 'true' : 'false' ?>" data-vote-button data-vote-value="<?= $value ?>">
            <?php else: ?>
                <a class="vote-button" href="<?= h(localized_path('/login')) ?>" aria-label="<?= h('Sign in to ' . strtolower($verb) . ' this ' . $label) ?>">
            <?php endif; ?>
                <svg class="vote-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= h($path) ?>"/></svg>
            <?php if ($signedIn): ?></button></form><?php else: ?></a><?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php
};

$renderReportButton = static function (string $type, int $id, string $label) use ($csrf): void {
    $action = '/flag/' . $type . '/' . $id;
    ?>
    <button type="button" class="button-secondary report-open" data-report-open data-report-action="<?= h($action) ?>" data-report-csrf="<?= h($csrf->field($action)) ?>"><?= h($label) ?></button>
    <?php
};

$renderShareLink = static function (string $url, string $title, string $label): void {
    ?>
    <span class="share-action">
        <a class="button-secondary share-link" href="<?= h($url) ?>" data-share-trigger data-share-url="<?= h($url) ?>" data-share-title="<?= h($title) ?>" data-share-copied="<?= h(t('questions.share.copied', 'Link copied')) ?>" data-share-failed="<?= h(t('questions.share.failed', 'Copy the link from your address bar')) ?>"><?= h($label) ?></a>
        <span class="share-status" data-share-status role="status" aria-live="polite"></span>
    </span>
    <?php
};

$renderReactions = static function (string $type, int $id) use ($authUser, $csrf, $reactionMap): void {
    $key = $type . ':' . $id;
    $items = is_array($reactionMap[$key] ?? null) ? $reactionMap[$key] : [];
    if ($items === []) {
        return;
    }

    ?>
    <div class="reaction-bar" role="group" aria-label="Reactions"<?= is_array($authUser) ? ' data-reaction-bar data-post-type="' . h($type) . '" data-post-id="' . $id . '"' : '' ?>>
        <?php foreach ($items as $reaction): ?>
            <?php
            $name = (string) ($reaction['name'] ?? 'Reaction');
            $iconPath = (string) ($reaction['icon_path'] ?? '');
            $action = '/post/' . $type . '/' . $id . '/react';
            $active = !empty($reaction['reacted']);
            $symbol = match (strtolower($name)) {
                'useful' => 'm4 8 2.5 2.5L12 5',
                'thanks' => 'M8 13 3 8a3 3 0 0 1 5-3 3 3 0 0 1 5 3z',
                default => 'M8 2l1.5 4.5L14 8l-4.5 1.5L8 14l-1.5-4.5L2 8l4.5-1.5z',
            };
            $fallback = '<svg class="reaction-glyph" viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $symbol . '"/></svg>';
            ?>
            <?php if (is_array($authUser)): ?>
                    <form method="post" action="<?= h($action) ?>" data-async-reaction-form data-reaction-id="<?= (int) $reaction['id'] ?>" data-reaction-weight="<?= (int) ($reaction['reputation_delta'] ?? 0) ?>">
                    <?= $csrf->field($action) ?>
                    <input type="hidden" name="reaction_type_id" value="<?= (int) $reaction['id'] ?>">
                    <button type="submit" class="reaction-button <?= $active ? 'is-active' : '' ?>" aria-pressed="<?= $active ? 'true' : 'false' ?>" title="<?= h($name) ?>" data-reaction-button>
                        <?php if ($iconPath !== ''): ?>
                            <?= uploaded_image($iconPath, 'reaction-icon', $name . ' reaction icon') ?>
                        <?php else: ?>
                            <?= $fallback ?>
                        <?php endif; ?>
                        <span><?= h($name) ?></span>
                        <strong data-reaction-count><?= (int) ($reaction['count'] ?? 0) ?></strong>
                    </button>
                </form>
            <?php else: ?>
                <a class="reaction-button reaction-pill" href="<?= h(localized_path('/login')) ?>" aria-label="<?= h('Sign in to react: ' . $name . ', ' . (int) ($reaction['count'] ?? 0)) ?>" data-reaction-weight="<?= (int) ($reaction['reputation_delta'] ?? 0) ?>">
                    <?php if ($iconPath !== ''): ?>
                        <?= uploaded_image($iconPath, 'reaction-icon', $name . ' reaction icon') ?>
                    <?php else: ?>
                        <?= $fallback ?>
                    <?php endif; ?>
                    <span><?= h($name) ?></span>
                    <strong data-reaction-count><?= (int) ($reaction['count'] ?? 0) ?></strong>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php
};

$canModerate = is_array($authUser) && in_array(active_role($authUser), ['moderator', 'administrator'], true);
$commentsOpen = empty($question['closed_at']);

$allCommentsById = [];
foreach (array_merge($questionComments, $answerComments) as $commentLookup) {
    $allCommentsById[(int) $commentLookup['id']] = $commentLookup;
}

$buildCommentTree = static function (array $comments): array {
    $children = [];
    $visibleIds = array_fill_keys(array_column($comments, 'id'), true);
    foreach ($comments as $comment) {
        $parentId = (int) ($comment['parent_id'] ?? 0);
        if (!isset($visibleIds[$parentId])) {
            $parentId = 0;
        }
        $children[$parentId][] = $comment;
    }

    return $children;
};

$questionCommentTree = $buildCommentTree($questionComments);
$answerCommentTrees = [];
foreach ($answerComments as $commentLookup) {
    $answerId = (int) ($commentLookup['answer_id'] ?? 0);
    $parentId = (int) ($commentLookup['parent_id'] ?? 0);
    $answerCommentTrees[$answerId][$parentId][] = $commentLookup;
}

$quotePreview = static function (string $body, int $wordLimit = 5): string {
    $words = preg_split('/\s+/', trim(strip_tags($body))) ?: [];
    $words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));
    if ($words === []) {
        return '';
    }

    $limited = array_slice($words, 0, $wordLimit);
    $suffix = count($words) > $wordLimit ? '...' : '';

    return implode(' ', $limited) . $suffix;
};

$renderComment = static function (array $comment, string $composerId, int $depth = 0) use ($authUser, $csrf, $renderVote, $renderReportButton, $renderShareLink, $markdown, $allCommentsById, $canModerate, $commentsOpen, $question, $quotePreview): void {
    $parent = isset($comment['parent_id']) ? ($allCommentsById[(int) $comment['parent_id']] ?? null) : null;
    $quoteBody = $quotePreview((string) $comment['body']);
    $depthClass = 'comment-depth-' . min(6, max(0, $depth));
    $commentUrl = url('/question/' . rawurlencode((string) $question['slug']) . '#comment-' . (int) $comment['id']);
    $commentTitle = strtr(t('questions.share.clarification_title', 'Clarification by :username', ['username' => (string) ($comment['username'] ?? '')]), [':username' => (string) ($comment['username'] ?? '')]);
    ?>
    <article id="comment-<?= (int) $comment['id'] ?>" class="comment <?= $comment['parent_id'] ? 'comment-reply' : '' ?> <?= h($depthClass) ?>">
        <span class="comment-avatar" aria-hidden="true">
            <?php if (!empty($comment['avatar_path'])): ?>
                <?= uploaded_image((string) $comment['avatar_path'], 'avatar', '') ?>
            <?php else: ?>
                <?= h(mb_strtoupper(mb_substr((string) $comment['username'], 0, 1))) ?>
            <?php endif; ?>
        </span>
        <div class="comment-content">
            <header class="comment-head">
                <span class="comment-author-wrap">
                    <a class="comment-author" href="<?= h(localized_path('/user/' . rawurlencode((string) $comment['username']))) ?>"><?= h((string) $comment['username']) ?></a>
                </span>
                <time class="comment-date" datetime="<?= h(str_replace(' ', 'T', (string) $comment['created_at'])) ?>"><?= h((string) $comment['created_at']) ?></time>
            </header>
            <?php if (is_array($parent)): ?>
                <a class="comment-parent-link" href="#comment-<?= (int) $parent['id'] ?>">Reply to <?= h((string) $parent['username']) ?></a>
            <?php elseif (!empty($comment['parent_id'])): ?>
                <p class="meta">Reply to an unavailable clarification</p>
            <?php endif; ?>
            <div class="comment-body"><?= $markdown((string) $comment['body']) ?></div>
            <div class="comment-actions">
                <?php $renderVote('comment', (int) $comment['id'], (int) ($comment['votes'] ?? 0), 'clarification'); ?>
                <?php $renderShareLink($commentUrl, $commentTitle, t('questions.share.action', 'Share')); ?>
                <?php if (is_array($authUser)): ?>
                    <?php if ($commentsOpen): ?>
                        <button type="button" class="button-secondary quote-comment" data-quote-comment data-quote-inline-target="<?= h($composerId) ?>" data-quote-parent="<?= (int) $comment['id'] ?>" data-quote-author="<?= h((string) $comment['username']) ?>" data-quote-body="<?= h($quoteBody) ?>">Reply</button>
                    <?php endif; ?>
                    <?php $renderReportButton('comment', (int) $comment['id'], 'Report'); ?>
                    <?php if ($canModerate): ?>
                        <details class="post-menu clarification-menu"><summary>Manage</summary><div class="post-menu-panel"><a class="button-secondary comment-mod-link" href="/moderator/comment/<?= (int) $comment['id'] ?>/edit">Edit clarification</a>
                        <form method="post" action="/moderator/comment/<?= (int) $comment['id'] ?>/delete" class="inline comment-mod-form">
                            <?= $csrf->field('/moderator/comment/' . (int) $comment['id'] . '/delete') ?>
                            <button type="submit" class="button-secondary">Delete</button>
                        </form></div></details>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
};

$renderCommentTree = null;
$renderCommentTree = static function (array $comment, string $composerId, array $childrenByParent, int $depth = 0) use (&$renderCommentTree, $renderComment): void {
    $renderComment($comment, $composerId, $depth);
    $commentId = (int) $comment['id'];
    if (empty($childrenByParent[$commentId])) {
        return;
    }
    ?>
    <details class="comment-branch comment-children-depth-<?= min(6, $depth + 1) ?>" open>
        <summary><span class="branch-toggle" aria-hidden="true"></span><span class="branch-label"><?= count($childrenByParent[$commentId]) ?> <?= count($childrenByParent[$commentId]) === 1 ? 'reply' : 'replies' ?></span></summary>
        <div class="comment-children">
        <?php foreach ($childrenByParent[$commentId] as $reply): ?>
            <?php $renderCommentTree($reply, $composerId, $childrenByParent, $depth + 1); ?>
        <?php endforeach; ?>
        </div>
    </details>
    <?php
};
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
$questionShareUrl = url('/question/' . rawurlencode((string) $question['slug']));
$questionShareTitle = (string) ($question['title'] ?? t('questions.share.question_title', 'Question'));
?>
<?php require BASE_PATH . '/views/partials/guest-welcome.php'; ?>
<?php if ($hasDynamicWidgets): ?>
    <div class="content-with-widgets question-with-widgets<?= h($widgetLayoutClass) ?>">
        <?php $widgetZone = 'left'; require BASE_PATH . '/views/partials/dynamic-widgets.php'; ?>
        <div class="dynamic-widget-main">
<?php endif; ?>
<div class="discussion-flow">
<article class="question-detail" aria-labelledby="question-title">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= h(localized_path('/')) ?>">Questions</a>
        <?php if (!empty($question['category_slug'])): ?>
            / <a href="<?= h(localized_path('/category/' . (string) $question['category_slug'])) ?>"><?= h((string) $question['category_name']) ?></a>
        <?php endif; ?>
    </nav>
    <header class="question-header">
        <div class="question-title-row discussion-heading">
            <h1 id="question-title"><?= h((string) $question['title']) ?></h1>
            <?php if ($canModerate): ?>
                <details class="post-menu"><?php $moderationSummary("Question moderation"); ?><div class="post-menu-panel">
                    <a class="button button-secondary" href="/moderator/question/<?= (int) $question['id'] ?>/edit">Edit question</a>
                    <form method="post" action="/moderator/question/<?= (int) $question['id'] ?>/close">
                        <?= $csrf->field('/moderator/question/' . (int) $question['id'] . '/close') ?>
                        <button type="submit">Close question</button>
                    </form>
                    <form method="post" action="/moderator/question/<?= (int) $question['id'] ?>/delete">
                        <?= $csrf->field('/moderator/question/' . (int) $question['id'] . '/delete') ?>
                        <button type="submit" class="button-danger">Delete question</button>
                    </form>
                </div></details>
            <?php endif; ?>
        </div>
        <p class="meta question-asked-meta">Asked by <a href="<?= h(localized_path('/user/' . rawurlencode((string) $question['username']))) ?>"><?= h((string) $question['username']) ?></a> &middot; <time datetime="<?= h(str_replace(' ', 'T', (string) $question['created_at'])) ?>"><?= h((string) $question['created_at']) ?></time></p>
        <?php if (is_array($duplicateCanonical ?? null)): ?>
            <aside class="duplicate-notice">
                <strong>Marked as duplicate</strong>
                <span>Canonical discussion: <a href="<?= h(localized_path('/question/' . (string) $duplicateCanonical['slug'])) ?>"><?= h((string) (($duplicateCanonical['title'] ?? '') ?: $duplicateCanonical['slug'])) ?></a></span>
            </aside>
        <?php endif; ?>
        <?php if (!empty($question['closed_at'])): ?>
            <p class="flash">Closed to new answers.</p>
        <?php endif; ?>
    </header>
    <div class="discussion-post">
        <div>
            <div class="post-body"><?= $markdown((string) $question['body']) ?></div>
            <div class="post-engagement"><?php $renderVote('question', (int) $question['id'], (int) $question['votes'], 'question'); ?><?php $renderReactions('question', (int) $question['id']); ?></div>
            <div class="question-content-meta">
                <div class="question-meta-left">

                    <?php if ($tags !== []): ?>
                        <div class="tags question-meta-tags">
                            <?php foreach ($tags as $tag): ?>
                                <a href="<?= h(localized_path('/tag/' . (string) $tag['slug'])) ?>">#<?= h((string) $tag['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="question-meta-right">
                    <div class="post-actions question-report-actions">
                        <?php if (!empty($discussionTextUrl)): ?>
                            <a class="discussion-text-link" href="<?= h($discussionTextUrl) ?>">Markdown</a>
                        <?php endif; ?>
                        <?php if ($answers !== []): ?>
                            <a class="button button-secondary answer-jump" href="#answers-heading"><?= count($answers) ?> <?= count($answers) === 1 ? 'answer' : 'answers' ?></a>
                        <?php endif; ?>
                        <?php $renderShareLink($questionShareUrl, $questionShareTitle, t('questions.share.question', 'Share question')); ?>
                        <?php if (is_array($authUser)): ?><?php $renderReportButton('question', (int) $question['id'], 'Report question'); ?><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <section class="comments" aria-label="Question clarifications">
        <?php if ($questionComments !== []): ?>
        <details class="clarification-list" open>
            <summary class="clarifications-heading">Question clarifications (<?= count($questionComments) ?>)</summary>
        <?php foreach (($questionCommentTree[0] ?? []) as $comment): ?>
            <div class="comment-thread">
                <?php $renderCommentTree($comment, 'question-comment-composer', $questionCommentTree); ?>
            </div>
        <?php endforeach; ?>
        </details>
        <?php endif; ?>
        <?php if (is_array($authUser) && $commentsOpen): ?>
            <details class="thread-reply-details comment-thread-composer" id="question-comment-composer">
                <summary>Add a clarification</summary>
                <form method="post" action="/question/<?= h((string) $question['slug']) ?>/comment" class="comment-form comment-composer" enctype="multipart/form-data">
                    <?= $csrf->field('/question/' . (string) $question['slug'] . '/comment') ?>
                    <input type="hidden" name="parent_id" value="0" data-quote-parent-input>
                    <input type="hidden" name="quoted_comment_id" value="0" data-quote-id-input>
                    <div class="quote-context" data-quote-context hidden><span data-quote-context-text></span><button type="button" class="button-secondary" data-clear-quote>Clear</button></div>
                    <textarea name="body" aria-label="Clarification text" rows="2" maxlength="2000" placeholder="Reply in this thread. Use @username to mention someone." data-mentionable></textarea>
                    <div class="comment-compose-tools">
                        <label class="attachment-control">Attach media
                            <input
                                name="media[]"
                                type="file"
                                accept="image/png,image/jpeg,image/webp,image/avif,image/gif,video/mp4,video/webm"
                                multiple
                                data-upload-input
                                data-upload-max-files="6"
                                data-upload-max-image-bytes="12000000"
                                data-upload-max-video-bytes="40000000"
                                data-upload-max-total-bytes="60000000"
                                data-upload-empty-message="Attach images or short videos to support your clarification."
                            >
                        </label>
                        <input name="video_urls" aria-label="Video links" inputmode="url" maxlength="1000" placeholder="YouTube or Vimeo URL">
                        <button type="submit">Reply</button>
                    </div>
                    <div class="upload-feedback upload-feedback-inline" data-upload-feedback>
                        <p class="field-help upload-feedback-summary" data-upload-summary>Attach images or short videos to support your clarification.</p>
                        <ul class="upload-feedback-list" data-upload-list hidden></ul>
                    </div>
                </form>
            </details>
        <?php elseif (is_array($authUser)): ?>
            <p class="flash">This question is closed to new clarifications.</p>
        <?php endif; ?>
    </section>
</article>

<section class="answers" aria-labelledby="answers-heading">
    <h2 id="answers-heading"><?= count($answers) ?> <?= count($answers) === 1 ? 'answer' : 'answers' ?></h2>
    <?php foreach ($answers as $answer): ?>
        <?php
        $isAccepted = (int) $answer['is_accepted'] === 1;
        $isCommunityChosen = !empty($answer['is_community_chosen']);
        $communityScore = (int) ($answer['community_score'] ?? ((int) ($answer['votes'] ?? 0) + (int) ($answer['reaction_score'] ?? 0)));
        $answerShareUrl = url('/question/' . rawurlencode((string) $question['slug']) . '#answer-' . (int) $answer['id']);
        $answerShareTitle = strtr(t('questions.share.answer_title', 'Answer by :username', ['username' => (string) ($answer['username'] ?? '')]), [':username' => (string) ($answer['username'] ?? '')]);
        $answerClasses = ['answer'];
        if ($isAccepted) {
            $answerClasses[] = 'answer-accepted';
        }
        if ($isCommunityChosen) {
            $answerClasses[] = 'answer-community-chosen';
        }
        ?>
        <article id="answer-<?= (int) $answer['id'] ?>" class="<?= h(implode(' ', $answerClasses)) ?>" data-answer-card data-answer-id="<?= (int) $answer['id'] ?>" data-answer-created="<?= (int) (strtotime((string) ($answer['created_at'] ?? '')) ?: PHP_INT_MAX) ?>" data-answer-votes="<?= (int) ($answer['votes'] ?? 0) ?>" data-answer-reaction-score="<?= (int) ($answer['reaction_score'] ?? 0) ?>" data-answer-reaction-count="<?= (int) ($answer['reaction_count'] ?? 0) ?>" data-community-score="<?= $communityScore ?>">
            <div class="discussion-post">
                <div>
                    <header class="discussion-heading answer-heading">
                        <p class="meta">
                            <?php if (!empty($answer['avatar_path'])): ?><?= uploaded_image((string) $answer['avatar_path'], 'avatar', '') ?><?php endif; ?>
                            Answered by <a href="<?= h(localized_path('/user/' . rawurlencode((string) $answer['username']))) ?>"><?= h((string) $answer['username']) ?></a> &middot; <time datetime="<?= h(str_replace(' ', 'T', (string) $answer['created_at'])) ?>"><?= h((string) $answer['created_at']) ?></time>
                        </p>
                        <?php $canAcceptAnswer = is_array($authUser) && (int) $authUser['id'] === (int) $question['user_id']; ?>
                        <?php if ($canAcceptAnswer || $canModerate): ?>
                            <details class="post-menu">
                                <?php $moderationSummary('Answer actions'); ?>
                                <div class="post-menu-panel">
                                    <?php if ($canAcceptAnswer): ?>
                                        <form method="post" action="/answer/<?= (int) $answer['id'] ?>/accept">
                                            <?= $csrf->field('/answer/' . (int) $answer['id'] . '/accept') ?>
                                            <button type="submit"><?= (int) $answer['is_accepted'] === 1 ? 'Accepted' : 'Accept answer' ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canModerate): ?>
                                        <?php if (empty($answer['verified_at'])): ?>
                                            <form method="post" action="/moderator/answer/<?= (int) $answer['id'] ?>/verify" class="answer-review-form">
                                                <?= $csrf->field('/moderator/answer/' . (int) $answer['id'] . '/verify') ?>
                                                <label>Review note <input name="review_note" maxlength="240" placeholder="Optional"></label>
                                                <button type="submit">Mark reviewed</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="/moderator/answer/<?= (int) $answer['id'] ?>/unverify">
                                                <?= $csrf->field('/moderator/answer/' . (int) $answer['id'] . '/unverify') ?>
                                                <button type="submit">Remove review</button>
                                            </form>
                                        <?php endif; ?>
                                        <a class="button button-secondary" href="/moderator/answer/<?= (int) $answer['id'] ?>/edit">Edit answer</a>
                                        <form method="post" action="/moderator/answer/<?= (int) $answer['id'] ?>/delete">
                                            <?= $csrf->field('/moderator/answer/' . (int) $answer['id'] . '/delete') ?>
                                            <button type="submit" class="button-danger">Delete answer</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    </header>
                    <div class="answer-labels">
                        <?php if ($isAccepted): ?><p class="accepted accepted-answer-label"><?= h(t('questions.answer.accepted', 'Accepted answer')) ?></p><?php endif; ?>
                        <p class="accepted community-answer-label" data-community-choice-label data-community-score-title="<?= h(t('questions.answer.community_score_title', 'Community score: :score from votes and reactions', ['score' => ':score'])) ?>" title="<?= h(t('questions.answer.community_score_title', 'Community score: :score from votes and reactions', ['score' => $communityScore])) ?>" <?= $isCommunityChosen ? '' : 'hidden' ?>>
                            <?= h(t('questions.answer.community_chosen', 'Community chosen')) ?>
                            <span data-community-score-text><?= $communityScore ?></span>
                        </p>
                        <?php if (!empty($answer['verified_at'])): ?><p class="accepted verified-answer-label"><?= h(t('questions.answer.expert_reviewed', 'Expert reviewed')) ?></p><?php endif; ?>
                    </div>
                    <div class="post-body"><?= $markdown((string) $answer['body']) ?></div>
                    <div class="post-engagement"><?php $renderVote('answer', (int) $answer['id'], (int) $answer['votes'], 'answer'); ?><?php $renderReactions('answer', (int) $answer['id']); ?></div>
                    <?php if (!empty($answer['verified_at'])): ?>
                        <p class="meta verified-review">
                    Reviewed by <a href="<?= h(localized_path('/user/' . rawurlencode((string) $answer['verified_by_username']))) ?>"><?= h((string) $answer['verified_by_username']) ?></a> &middot; <?= h((string) $answer['verified_at']) ?>
                            <?php if (!empty($answer['review_note'])): ?><br><?= h((string) $answer['review_note']) ?><?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <div class="answer-action-layout">
                        <div class="post-actions answer-report-actions">
                            <?php $renderShareLink($answerShareUrl, $answerShareTitle, t('questions.share.answer', 'Share answer')); ?>
                            <?php if (is_array($authUser)): ?><?php $renderReportButton('answer', (int) $answer['id'], 'Report answer'); ?><?php endif; ?>
                        </div>
                    </div>
                    <section class="comments" aria-label="Answer clarifications">
                        <?php $answerCommentTree = $answerCommentTrees[(int) $answer['id']] ?? []; ?>
                        <?php if ($answerCommentTree !== []): ?>
                        <details class="clarification-list" open>
                            <summary class="clarifications-heading">Answer clarifications</summary>
                        <?php foreach (($answerCommentTree[0] ?? []) as $comment): ?>
                            <div class="comment-thread">
                                <?php $renderCommentTree($comment, 'answer-' . (int) $answer['id'] . '-comment-composer', $answerCommentTree); ?>
                            </div>
                        <?php endforeach; ?>
                        </details>
                        <?php endif; ?>
                        <?php if (is_array($authUser) && $commentsOpen): ?>
                            <details class="thread-reply-details comment-thread-composer" id="answer-<?= (int) $answer['id'] ?>-comment-composer">
                                <summary>Add a clarification</summary>
                                <form method="post" action="/answer/<?= (int) $answer['id'] ?>/comment" class="comment-form comment-composer" enctype="multipart/form-data">
                                    <?= $csrf->field('/answer/' . (int) $answer['id'] . '/comment') ?>
                                    <input type="hidden" name="parent_id" value="0" data-quote-parent-input>
                                    <input type="hidden" name="quoted_comment_id" value="0" data-quote-id-input>
                                    <div class="quote-context" data-quote-context hidden><span data-quote-context-text></span><button type="button" class="button-secondary" data-clear-quote>Clear</button></div>
                                    <textarea name="body" aria-label="Clarification text" rows="2" maxlength="2000" placeholder="Reply in this thread. Use @username to mention someone." data-mentionable></textarea>
                                    <div class="comment-compose-tools">
                                        <label class="attachment-control">Attach media
                                            <input
                                                name="media[]"
                                                type="file"
                                                accept="image/png,image/jpeg,image/webp,image/avif,image/gif,video/mp4,video/webm"
                                                multiple
                                                data-upload-input
                                                data-upload-max-files="6"
                                                data-upload-max-image-bytes="12000000"
                                                data-upload-max-video-bytes="40000000"
                                                data-upload-max-total-bytes="60000000"
                                                data-upload-empty-message="Attach images or short videos to support your clarification."
                                            >
                                        </label>
                                        <input name="video_urls" aria-label="Video links" inputmode="url" maxlength="1000" placeholder="YouTube or Vimeo URL">
                                        <button type="submit">Reply</button>
                                    </div>
                                    <div class="upload-feedback upload-feedback-inline" data-upload-feedback>
                                        <p class="field-help upload-feedback-summary" data-upload-summary>Attach images or short videos to support your clarification.</p>
                                        <ul class="upload-feedback-list" data-upload-list hidden></ul>
                                    </div>
                                </form>
                            </details>
                        <?php elseif (is_array($authUser)): ?>
                            <p class="flash">This question is closed to new clarifications.</p>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<?php if (is_array($authUser) && empty($question['closed_at'])): ?>
    <section class="answer-form">
        <h2>Your answer</h2>
        <form method="post" action="/question/<?= h((string) $question['slug']) ?>/answer" enctype="multipart/form-data" class="ask-form answer-compose">
            <?= $csrf->field('/question/' . (string) $question['slug'] . '/answer') ?>
            <div class="form-row form-row-full">
                <label class="answer-body-field"><span class="sr-only">Answer text</span>
                    <textarea name="body" rows="6" minlength="10" required data-markdown-editor data-editor-context="answer" data-editor-autosave="1" data-editor-min-words="8" placeholder="Share the steps, tradeoffs, source, or exact fix that helped you solve it." data-mentionable></textarea>
                    <span class="field-help">Use headings, lists, links, quotes, and code to make the answer easy to scan.</span>
                </label>
            </div>
            <details class="answer-attachments">
                <summary>Add media or video links</summary>
                <div class="answer-attachment-fields">
                <label>Media
                    <input
                        name="media[]"
                        type="file"
                        accept="image/png,image/jpeg,image/webp,image/avif,image/gif,video/mp4,video/webm"
                        multiple
                        data-upload-input
                        data-upload-max-files="6"
                        data-upload-max-image-bytes="12000000"
                        data-upload-max-video-bytes="40000000"
                        data-upload-max-total-bytes="60000000"
                        data-upload-empty-message="Attach images or short videos that make the answer easier to follow."
                    >
                    <div class="upload-feedback" data-upload-feedback>
                        <p class="field-help upload-feedback-summary" data-upload-summary>Attach images or short videos that make the answer easier to follow.</p>
                        <ul class="upload-feedback-list" data-upload-list hidden></ul>
                    </div>
                </label>
                <label>Video links
                    <input name="video_urls" inputmode="url" maxlength="1000" placeholder="YouTube or Vimeo URL">
                </label>
                </div>
            </details>
            <div class="answer-submit"><button type="submit">Post answer</button></div>
        </form>
    </section>
<?php elseif (is_array($authUser)): ?>
    <p class="flash">This question is closed to new answers.</p>
<?php else: ?>
            <p><a href="<?= h(localized_path('/login')) ?>">Sign in</a> to answer.</p>
<?php endif; ?>
<?php if ($related !== []): ?>
    <aside class="related-questions" aria-label="Related questions">
        <header class="related-header">
            <h2>Related Questions</h2>
            <p class="related-subtitle">You might also find these helpful</p>
        </header>
        <div class="related-list">
            <?php foreach (array_slice($related, 0, 4) as $item):
                $answerCount  = (int) ($item['answer_count'] ?? 0);
                $votes        = (int) ($item['votes'] ?? 0);
                $hasAccepted  = !empty($item['has_accepted']);
                $hasAnswers   = $answerCount > 0;
                $statusClass  = $hasAccepted ? 'related-answers-accepted' : ($hasAnswers ? 'related-answers-open' : 'related-answers-none');
            ?>
                            <a class="related-item" href="<?= h(localized_path('/question/' . (string) $item['slug'])) ?>">
                    <div class="related-item-counters">
                        <span class="related-counter related-counter-votes" title="<?= (int) $votes ?> vote<?= $votes !== 1 ? 's' : '' ?>">
                            <strong><?= (int) $votes ?></strong>
                            <span>vote<?= $votes !== 1 ? 's' : '' ?></span>
                        </span>
                        <span class="related-counter related-counter-answers <?= $statusClass ?>" title="<?= (int) $answerCount ?> answer<?= $answerCount !== 1 ? 's' : '' ?><?= $hasAccepted ? ' (accepted)' : '' ?>">
                            <strong><?= (int) $answerCount ?></strong>
                            <span>ans</span>
                        </span>
                    </div>
                    <h3 class="related-item-title"><?= h((string) $item['title']) ?></h3>
                    <?php if ($hasAccepted): ?>
                        <span class="related-accepted-mark" aria-label="Has accepted answer" title="Has accepted answer">&#10003;</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>
<?php endif; ?>

</div>
<?php if ($hasDynamicWidgets): ?>
        </div>
        <?php $widgetZone = 'right'; require BASE_PATH . '/views/partials/dynamic-widgets.php'; ?>
    </div>
<?php endif; ?>
