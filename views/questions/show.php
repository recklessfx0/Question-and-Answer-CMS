<?php

declare(strict_types=1);

$renderVote = static function (string $type, int $id, int $votes, string $label) use ($csrf): void {
    $upIcon = $type === 'question' ? '&uarr;' : '&#128077;';
    $downIcon = $type === 'question' ? '&darr;' : '&#128078;';
    ?>
    <div class="vote-box <?= $type === 'comment' ? 'vote-box-compact' : '' ?>">
        <form method="post" action="/post/<?= h($type) ?>/<?= $id ?>/vote">
            <?= $csrf->field('/post/' . $type . '/' . $id . '/vote') ?>
            <input type="hidden" name="value" value="1"><button class="vote-button vote-up" aria-label="Upvote <?= h($label) ?>" title="Upvote"><span aria-hidden="true"><?= $upIcon ?></span></button>
        </form>
        <strong><?= $votes ?></strong>
        <form method="post" action="/post/<?= h($type) ?>/<?= $id ?>/vote">
            <?= $csrf->field('/post/' . $type . '/' . $id . '/vote') ?>
            <input type="hidden" name="value" value="-1"><button class="vote-button vote-down" aria-label="Downvote <?= h($label) ?>" title="Downvote"><span aria-hidden="true"><?= $downIcon ?></span></button>
        </form>
    </div>
    <?php
};

$renderReportButton = static function (string $type, int $id, string $label) use ($csrf): void {
    $action = '/flag/' . $type . '/' . $id;
    ?>
    <button type="button" class="button-secondary report-open" data-report-open data-report-action="<?= h($action) ?>" data-report-csrf="<?= h($csrf->field($action)) ?>"><?= h($label) ?></button>
    <?php
};

$renderReactions = static function (string $type, int $id) use ($authUser, $csrf, $reactionMap): void {
    $key = $type . ':' . $id;
    $items = is_array($reactionMap[$key] ?? null) ? $reactionMap[$key] : [];
    if ($items === []) {
        return;
    }

    ?>
    <div class="reaction-bar" aria-label="Reactions">
        <?php foreach ($items as $reaction): ?>
            <?php
            $name = (string) ($reaction['name'] ?? 'Reaction');
            $iconPath = (string) ($reaction['icon_path'] ?? '');
            $action = '/post/' . $type . '/' . $id . '/react';
            $active = !empty($reaction['reacted']);
            ?>
            <?php if (is_array($authUser)): ?>
                <form method="post" action="<?= h($action) ?>">
                    <?= $csrf->field($action) ?>
                    <input type="hidden" name="reaction_type_id" value="<?= (int) $reaction['id'] ?>">
                    <button type="submit" class="reaction-button <?= $active ? 'is-active' : '' ?>" aria-pressed="<?= $active ? 'true' : 'false' ?>" title="<?= h($name) ?>">
                        <?php if ($iconPath !== ''): ?>
                            <?= uploaded_image($iconPath, 'reaction-icon', $name . ' reaction icon') ?>
                        <?php else: ?>
                            <span class="reaction-icon-fallback"><?= h(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
                        <?php endif; ?>
                        <span><?= h($name) ?></span>
                        <strong><?= (int) ($reaction['count'] ?? 0) ?></strong>
                    </button>
                </form>
            <?php else: ?>
                <span class="reaction-button reaction-pill" title="<?= h($name) ?>">
                    <?php if ($iconPath !== ''): ?>
                        <?= uploaded_image($iconPath, 'reaction-icon', $name . ' reaction icon') ?>
                    <?php else: ?>
                        <span class="reaction-icon-fallback"><?= h(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
                    <?php endif; ?>
                    <span><?= h($name) ?></span>
                    <strong><?= (int) ($reaction['count'] ?? 0) ?></strong>
                </span>
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
    foreach ($comments as $comment) {
        $parentId = (int) ($comment['parent_id'] ?? 0);
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

$renderComment = static function (array $comment, string $composerId, string $action, string $csrfAction, int $depth = 0) use ($authUser, $csrf, $renderVote, $renderReportButton, $markdown, $allCommentsById, $canModerate, $commentsOpen): void {
    $parent = isset($comment['parent_id']) ? ($allCommentsById[(int) $comment['parent_id']] ?? null) : null;
    $quoteBody = trim(excerpt((string) $comment['body'], 260));
    $replyId = 'comment-' . (int) $comment['id'] . '-reply-composer';
    $depthClass = 'comment-depth-' . min(6, max(0, $depth));
    ?>
    <article id="comment-<?= (int) $comment['id'] ?>" class="comment <?= $comment['parent_id'] ? 'comment-reply' : '' ?> <?= h($depthClass) ?>">
        <?php $renderVote('comment', (int) $comment['id'], (int) ($comment['votes'] ?? 0), 'clarification'); ?>
        <div class="comment-content">
            <header class="comment-head">
                <span class="comment-author-wrap">
                    <?php if (!empty($comment['avatar_path'])): ?><?= uploaded_image((string) $comment['avatar_path'], 'avatar', (string) $comment['username'] . ' avatar') ?><?php endif; ?>
                    <strong class="comment-author"><?= h((string) $comment['username']) ?></strong>
                </span>
                <span class="comment-date"><?= h((string) ($comment['created_at'] ?? '')) ?></span>
            </header>
            <?php if (is_array($parent)): ?>
                <blockquote class="comment-quote">Quoted <?= h((string) $parent['username']) ?>: <?= h(excerpt((string) $parent['body'], 140)) ?></blockquote>
            <?php endif; ?>
            <div class="comment-body"><?= $markdown((string) $comment['body']) ?></div>
            <?php if (is_array($authUser)): ?>
                <div class="comment-actions">
                    <?php if ($commentsOpen): ?>
                        <button type="button" class="button-secondary quote-comment" data-quote-comment data-quote-inline-target="<?= h($replyId) ?>" data-quote-parent="<?= (int) $comment['id'] ?>" data-quote-author="<?= h((string) $comment['username']) ?>" data-quote-body="<?= h($quoteBody) ?>">Quote</button>
                    <?php endif; ?>
                    <?php $renderReportButton('comment', (int) $comment['id'], 'Report'); ?>
                    <?php if ($canModerate): ?>
                        <a class="button-secondary comment-mod-link" href="/moderator/comment/<?= (int) $comment['id'] ?>/edit">Edit</a>
                        <form method="post" action="/moderator/comment/<?= (int) $comment['id'] ?>/delete" class="inline comment-mod-form">
                            <?= $csrf->field('/moderator/comment/' . (int) $comment['id'] . '/delete') ?>
                            <button type="submit" class="button-secondary">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if ($commentsOpen): ?>
                    <details class="thread-reply-details" id="<?= h($replyId) ?>">
                        <summary>Reply in this thread</summary>
                        <form method="post" action="<?= h($action) ?>" class="comment-form comment-form-inline" enctype="multipart/form-data">
                            <?= $csrf->field($csrfAction) ?>
                            <input type="hidden" name="parent_id" value="<?= (int) $comment['id'] ?>" data-quote-parent-input>
                            <input type="hidden" name="quoted_comment_id" value="<?= (int) $comment['id'] ?>" data-quote-id-input>
                            <div class="quote-context" data-quote-context hidden><span data-quote-context-text></span><button type="button" class="button-secondary" data-clear-quote>Clear</button></div>
                            <textarea name="body" rows="2" maxlength="2000" placeholder="Reply to <?= h((string) $comment['username']) ?>"></textarea>
                            <div class="comment-compose-tools">
                                <label class="attachment-control">Attach media
                                    <input name="media[]" type="file" accept="image/png,image/jpeg,image/webp,image/avif,image/gif,video/mp4,video/webm" multiple>
                                </label>
                                <input name="video_urls" maxlength="1000" placeholder="YouTube or Vimeo URL">
                                <button type="submit">Reply</button>
                            </div>
                        </form>
                    </details>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </article>
    <?php
};

$renderCommentTree = null;
$renderCommentTree = static function (array $comment, string $composerId, string $action, string $csrfAction, array $childrenByParent, int $depth = 0) use (&$renderCommentTree, $renderComment): void {
    $renderComment($comment, $composerId, $action, $csrfAction, $depth);
    $commentId = (int) $comment['id'];
    if (empty($childrenByParent[$commentId])) {
        return;
    }
    ?>
    <div class="comment-children comment-children-depth-<?= min(6, $depth + 1) ?>">
        <?php foreach ($childrenByParent[$commentId] as $reply): ?>
            <?php $renderCommentTree($reply, $composerId, $action, $csrfAction, $childrenByParent, $depth + 1); ?>
        <?php endforeach; ?>
    </div>
    <?php
};
?>
<article class="question-detail">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Questions</a>
        <?php if (!empty($question['category_slug'])): ?>
            / <a href="/category/<?= h((string) $question['category_slug']) ?>"><?= h((string) $question['category_name']) ?></a>
        <?php endif; ?>
    </nav>
    <header class="question-header">
        <div class="question-title-row">
            <h1><?= h((string) $question['title']) ?></h1>
            <?php if ($canModerate): ?>
                <div class="moderation-actions question-top-actions" aria-label="Question moderation actions">
                    <a class="button button-secondary" href="/moderator/question/<?= (int) $question['id'] ?>/edit">Edit question</a>
                    <form method="post" action="/moderator/question/<?= (int) $question['id'] ?>/close">
                        <?= $csrf->field('/moderator/question/' . (int) $question['id'] . '/close') ?>
                        <button type="submit">Close question</button>
                    </form>
                    <form method="post" action="/moderator/question/<?= (int) $question['id'] ?>/delete">
                        <?= $csrf->field('/moderator/question/' . (int) $question['id'] . '/delete') ?>
                        <button type="submit" class="button-danger">Delete question</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($question['closed_at'])): ?>
            <p class="flash">Closed to new answers.</p>
        <?php endif; ?>
    </header>
    <div class="post-grid">
        <?php $renderVote('question', (int) $question['id'], (int) $question['votes'], 'question'); ?>
        <div>
            <div class="post-body"><?= $markdown((string) $question['body']) ?></div>
            <?php $renderReactions('question', (int) $question['id']); ?>
            <div class="question-content-meta">
                <div class="question-meta-left">
                    <?php if (!empty($question['category_slug'])): ?>
                        <p class="meta question-category-meta">Category <a href="/category/<?= h((string) $question['category_slug']) ?>"><?= h((string) $question['category_name']) ?></a></p>
                    <?php endif; ?>
                    <?php if ($tags !== []): ?>
                        <div class="tags question-meta-tags">
                            <?php foreach ($tags as $tag): ?>
                                <a href="/tag/<?= h((string) $tag['slug']) ?>">#<?= h((string) $tag['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="question-meta-right">
                    <p class="meta question-asked-meta">Asked by <a href="/user/<?= h((string) $question['username']) ?>"><?= h((string) $question['username']) ?></a> &middot; <?= h((string) $question['created_at']) ?></p>
                    <?php if (is_array($authUser)): ?><div class="post-actions question-report-actions"><?php $renderReportButton('question', (int) $question['id'], 'Report question'); ?></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <section class="comments" aria-label="Question clarifications">
        <?php foreach (($questionCommentTree[0] ?? []) as $comment): ?>
            <div class="comment-thread">
                <?php $renderCommentTree($comment, 'question-comment-composer', '/question/' . (string) $question['slug'] . '/comment', '/question/' . (string) $question['slug'] . '/comment', $questionCommentTree); ?>
            </div>
        <?php endforeach; ?>
        <?php if (is_array($authUser) && $commentsOpen): ?>
            <form method="post" action="/question/<?= h((string) $question['slug']) ?>/comment" class="comment-form comment-composer" enctype="multipart/form-data" id="question-comment-composer">
                <?= $csrf->field('/question/' . (string) $question['slug'] . '/comment') ?>
                <input type="hidden" name="parent_id" value="0" data-quote-parent-input>
                <input type="hidden" name="quoted_comment_id" value="0" data-quote-id-input>
                <div class="quote-context" data-quote-context hidden><span data-quote-context-text></span><button type="button" class="button-secondary" data-clear-quote>Clear</button></div>
                <textarea name="body" rows="2" maxlength="2000" placeholder="Add a clarification or quote someone above"></textarea>
                <div class="comment-compose-tools">
                    <label class="attachment-control">Attach media
                        <input name="media[]" type="file" accept="image/png,image/jpeg,image/webp,image/avif,image/gif,video/mp4,video/webm" multiple>
                    </label>
                    <input name="video_urls" maxlength="1000" placeholder="YouTube or Vimeo URL">
                    <button type="submit">Comment</button>
                </div>
            </form>
        <?php elseif (is_array($authUser)): ?>
            <p class="flash">This question is closed to new clarifications.</p>
        <?php endif; ?>
    </section>
</article>

<section class="answers" aria-label="Answers">
    <h2><?= count($answers) ?> Answers</h2>
    <?php foreach ($answers as $answer): ?>
        <article id="answer-<?= (int) $answer['id'] ?>" class="answer <?= (int) $answer['is_accepted'] === 1 ? 'answer-accepted' : '' ?>">
            <div class="post-grid">
                <?php $renderVote('answer', (int) $answer['id'], (int) $answer['votes'], 'answer'); ?>
                <div>
                    <div class="answer-labels">
                        <?php if ((int) $answer['is_accepted'] === 1): ?><p class="accepted accepted-answer-label">Accepted answer</p><?php endif; ?>
                        <?php if (!empty($answer['verified_at'])): ?><p class="accepted verified-answer-label">Expert reviewed</p><?php endif; ?>
                    </div>
                    <div class="post-body"><?= $markdown((string) $answer['body']) ?></div>
                    <?php $renderReactions('answer', (int) $answer['id']); ?>
                    <p class="meta">
                        <?php if (!empty($answer['avatar_path'])): ?><?= uploaded_image((string) $answer['avatar_path'], 'avatar', (string) $answer['username'] . ' avatar') ?><?php endif; ?>
                        Answered by <a href="/user/<?= h((string) $answer['username']) ?>"><?= h((string) $answer['username']) ?></a> &middot; <?= h((string) $answer['created_at']) ?>
                    </p>
                    <?php if (!empty($answer['verified_at'])): ?>
                        <p class="meta verified-review">
                            Reviewed by <a href="/user/<?= h((string) $answer['verified_by_username']) ?>"><?= h((string) $answer['verified_by_username']) ?></a> &middot; <?= h((string) $answer['verified_at']) ?>
                            <?php if (!empty($answer['review_note'])): ?><br><?= h((string) $answer['review_note']) ?><?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if (is_array($authUser)): ?><div class="post-actions"><?php $renderReportButton('answer', (int) $answer['id'], 'Report answer'); ?></div><?php endif; ?>
                    <?php if ($canModerate): ?>
                        <div class="moderation-actions inline-moderation-actions">
                            <a class="button button-secondary" href="/moderator/answer/<?= (int) $answer['id'] ?>/edit">Edit answer</a>
                            <form method="post" action="/moderator/answer/<?= (int) $answer['id'] ?>/delete">
                                <?= $csrf->field('/moderator/answer/' . (int) $answer['id'] . '/delete') ?>
                                <button type="submit">Delete answer</button>
                            </form>
                        </div>
                    <?php endif; ?>
                    <?php if (is_array($authUser) && in_array(active_role($authUser), ['moderator', 'administrator'], true)): ?>
                        <div class="moderation-actions answer-review-actions">
                            <?php if (empty($answer['verified_at'])): ?>
                                <form method="post" action="/moderator/answer/<?= (int) $answer['id'] ?>/verify">
                                    <?= $csrf->field('/moderator/answer/' . (int) $answer['id'] . '/verify') ?>
                                    <input name="review_note" maxlength="240" placeholder="Optional review note">
                                    <button type="submit">Mark reviewed</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="/moderator/answer/<?= (int) $answer['id'] ?>/unverify">
                                    <?= $csrf->field('/moderator/answer/' . (int) $answer['id'] . '/unverify') ?>
                                    <button type="submit">Remove review</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (is_array($authUser) && (int) $authUser['id'] === (int) $question['user_id']): ?>
                        <form method="post" action="/answer/<?= (int) $answer['id'] ?>/accept" class="inline">
                            <?= $csrf->field('/answer/' . (int) $answer['id'] . '/accept') ?>
                            <button type="submit">Accept answer</button>
                        </form>
                    <?php endif; ?>
                    <section class="comments" aria-label="Answer clarifications">
                        <?php $answerCommentTree = $answerCommentTrees[(int) $answer['id']] ?? []; ?>
                        <?php foreach (($answerCommentTree[0] ?? []) as $comment): ?>
                            <div class="comment-thread">
                                <?php $renderCommentTree($comment, 'answer-' . (int) $answer['id'] . '-comment-composer', '/answer/' . (int) $answer['id'] . '/comment', '/answer/' . (int) $answer['id'] . '/comment', $answerCommentTree); ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (is_array($authUser) && $commentsOpen): ?>
                            <form method="post" action="/answer/<?= (int) $answer['id'] ?>/comment" class="comment-form comment-composer" enctype="multipart/form-data" id="answer-<?= (int) $answer['id'] ?>-comment-composer">
                                <?= $csrf->field('/answer/' . (int) $answer['id'] . '/comment') ?>
                                <input type="hidden" name="parent_id" value="0" data-quote-parent-input>
                                <input type="hidden" name="quoted_comment_id" value="0" data-quote-id-input>
                                <div class="quote-context" data-quote-context hidden><span data-quote-context-text></span><button type="button" class="button-secondary" data-clear-quote>Clear</button></div>
                                <textarea name="body" rows="2" maxlength="2000" placeholder="Add a clarification or quote someone above"></textarea>
                                <div class="comment-compose-tools">
                                    <label class="attachment-control">Attach media
                                        <input name="media[]" type="file" accept="image/png,image/jpeg,image/webp,image/avif,image/gif,video/mp4,video/webm" multiple>
                                    </label>
                                    <input name="video_urls" maxlength="1000" placeholder="YouTube or Vimeo URL">
                                    <button type="submit">Comment</button>
                                </div>
                            </form>
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
                <label>Your answer
                    <textarea name="body" rows="10" minlength="10" required data-markdown-editor placeholder="Share the steps, tradeoffs, or source that helped you solve it."></textarea>
                </label>
            </div>
            <div class="form-row form-row-actions">
                <label>Media
                    <input name="media[]" type="file" accept="image/png,image/jpeg,image/webp,image/avif,image/gif,video/mp4,video/webm" multiple>
                </label>
                <label>Video links
                    <input name="video_urls" maxlength="1000" placeholder="YouTube or Vimeo URL">
                </label>
                <button type="submit">Post answer</button>
            </div>
        </form>
    </section>
<?php elseif (is_array($authUser)): ?>
    <p class="flash">This question is closed to new answers.</p>
<?php else: ?>
    <p><a href="/login">Sign in</a> to answer.</p>
<?php endif; ?>

<?php if ($related !== []): ?>
    <aside class="related">
        <h2>Related questions</h2>
        <?php foreach ($related as $item): ?>
            <a href="/question/<?= h((string) $item['slug']) ?>"><?= h((string) $item['title']) ?></a>
        <?php endforeach; ?>
    </aside>
<?php endif; ?>
