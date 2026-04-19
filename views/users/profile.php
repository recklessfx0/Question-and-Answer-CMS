<?php declare(strict_types=1); ?>
<?php
$progress = max(0, min(100, (int) ($rank['progress'] ?? 0)));
$stats = is_array($activityStats ?? null) ? $activityStats : ['questions' => 0, 'answers' => 0, 'comments' => 0];
$formatDate = static fn (mixed $value): string => date('M j, Y', strtotime((string) $value) ?: time());
$websiteUrl = safe_external_url((string) ($profile['website_url'] ?? ''));
$isBanned = !empty($profile['banned_at']);
$isDeleted = !empty($profile['deleted_at']);
$participantsByQuestion = is_array($participantMap ?? null) ? $participantMap : [];
$renderParticipants = static function (array $participants): void {
    if ($participants === []) {
        return;
    }
    ?>
    <div class="profile-participants" aria-label="Participants">
        <?php foreach ($participants as $participant): ?>
            <a class="profile-participant" href="/user/<?= rawurlencode((string) ($participant['username'] ?? '')) ?>" title="<?= h((string) ($participant['username'] ?? '')) ?>">
                <?php if (!empty($participant['avatar_path'])): ?>
                    <?= uploaded_image((string) $participant['avatar_path'], 'profile-participant-avatar', (string) $participant['username'] . ' avatar') ?>
                <?php else: ?>
                    <span class="profile-participant-avatar profile-participant-initial" aria-hidden="true"><?= h(strtoupper(substr((string) ($participant['username'] ?? '?'), 0, 1))) ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
};
?>

<section class="profile-hero">
    <div class="profile-hero-main">
        <div class="profile-identity">
            <?php if (!empty($profile['avatar_path'])): ?>
                <?= uploaded_image((string) $profile['avatar_path'], 'avatar avatar-xl', (string) $profile['username'] . ' avatar') ?>
            <?php else: ?>
                <div class="avatar avatar-xl profile-initial" aria-hidden="true"><?= h(strtoupper(substr((string) $profile['username'], 0, 1))) ?></div>
            <?php endif; ?>
            <div class="profile-title">
                <p class="eyebrow"><?= h((string) $profile['role']) ?></p>
                <h1><?= h((string) $profile['username']) ?></h1>
                <?php if ($isBanned || $isDeleted): ?>
                    <div class="profile-status-row">
                        <?php if ($isBanned): ?><span class="profile-status-badge">Banned</span><?php endif; ?>
                        <?php if ($isDeleted): ?><span class="profile-status-badge">Deleted Account</span><?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="profile-stats" aria-label="Profile activity totals">
                    <div><strong><?= (int) $stats['questions'] ?></strong><span>Questions</span></div>
                    <div><strong><?= (int) $stats['answers'] ?></strong><span>Answers</span></div>
                    <div><strong><?= (int) $stats['comments'] ?></strong><span>Clarifications</span></div>
                    <div><strong><?= (int) $profile['reputation'] ?></strong><span>Reputation</span></div>
                </div>
                <p class="meta">Joined <?= h($formatDate($profile['created_at'] ?? 'now')) ?></p>
            </div>
        </div>
        <?php if (!empty($profile['bio'])): ?>
            <p class="profile-bio"><?= h((string) $profile['bio']) ?></p>
        <?php endif; ?>
        <?php if (is_array($authUser) && (int) ($authUser['id'] ?? 0) !== (int) $profile['id']): ?>
            <?php $reportAction = '/flag/user/' . (int) $profile['id']; ?>
            <button type="button" class="button-secondary report-open" data-report-open data-report-action="<?= h($reportAction) ?>" data-report-csrf="<?= h($csrf->field($reportAction)) ?>">Report user</button>
        <?php endif; ?>
    </div>
    <div class="profile-hero-panels">
        <aside class="profile-overview" aria-label="Profile overview">
            <div class="profile-overview-level">
                <div class="profile-overview-rank">
                    <?php if (!empty($rank['icon_path'])): ?>
                        <?= uploaded_image((string) $rank['icon_path'], 'level-icon level-icon-large', 'Level ' . (string) ($rank['level'] ?? 1) . ' ' . (string) ($rank['title'] ?? 'Member') . ' icon') ?>
                    <?php endif; ?>
                    <div class="profile-overview-title">
                        <span class="meta">Level <?= (int) ($rank['level'] ?? 1) ?></span>
                        <strong><?= h((string) ($rank['title'] ?? 'Member')) ?></strong>
                    </div>
                </div>
                <div class="profile-progress-wrap">
                    <div class="profile-progress-label">
                        <span><?= (int) $profile['reputation'] ?> reputation</span>
                        <span><?= $progress ?>%</span>
                    </div>
                    <progress class="profile-progress" max="100" value="<?= $progress ?>" aria-label="Level progress"><?= $progress ?>%</progress>
                </div>
            </div>
            <dl class="profile-facts profile-overview-facts">
                <div><dt>Role</dt><dd><?= h((string) $profile['role']) ?></dd></div>
                <?php if (!empty($profile['expertise'])): ?><div><dt>Expertise</dt><dd><?= h((string) $profile['expertise']) ?></dd></div><?php endif; ?>
                <?php if (!empty($profile['credentials'])): ?><div><dt>Credentials</dt><dd><?= h((string) $profile['credentials']) ?></dd></div><?php endif; ?>
                <?php if ($websiteUrl !== ''): ?><div><dt>Website</dt><dd><a href="<?= h($websiteUrl) ?>" rel="nofollow ugc me noopener noreferrer">Visit</a></dd></div><?php endif; ?>
            </dl>
        </aside>
    </div>
</section>

<?php if ($badges !== []): ?>
    <section class="profile-section">
        <h2>Badges</h2>
        <div class="tags">
            <?php foreach ($badges as $badge): ?>
                <span title="<?= h((string) ($badge['description'] ?? '')) ?>"><?= h((string) $badge['name']) ?></span>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="profile-section">
    <div class="section-head">
        <h2>Last activity</h2>
        <p class="meta">Showing the most recent 10 items</p>
    </div>
    <?php if ($activity === []): ?>
        <p class="meta">No public activity yet.</p>
    <?php endif; ?>
    <div class="profile-timeline">
        <?php foreach ($activity as $item): ?>
            <?php $participants = $participantsByQuestion[(int) ($item['question_id'] ?? 0)] ?? []; ?>
            <article class="profile-stream-item profile-stream-item-activity">
                <div class="profile-stream-body">
                    <h3><a href="/question/<?= h((string) $item['slug']) ?>"><?= h((string) $item['title']) ?></a></h3>
                    <p><?= h(excerpt((string) $item['summary'], 170)) ?></p>
                    <div class="profile-stream-meta">
                        <span><?= h($formatDate($item['created_at'] ?? 'now')) ?></span>
                        <?php $renderParticipants($participants); ?>
                    </div>
                </div>
                <span class="activity-type profile-stream-status"><?= h((string) $item['label']) ?></span>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="profile-section">
    <div class="section-head">
        <h2>Last questions</h2>
        <p class="meta">Showing the most recent 10 items</p>
    </div>
    <?php if ($questions === []): ?>
        <p class="meta">No questions yet.</p>
    <?php endif; ?>
    <div class="profile-list">
        <?php foreach ($questions as $question): ?>
            <?php $participants = $participantsByQuestion[(int) ($question['id'] ?? 0)] ?? []; ?>
            <article class="profile-stream-item">
                <div class="profile-stream-side profile-stream-score">
                    <strong><?= (int) $question['votes'] ?></strong>
                    <span>votes</span>
                </div>
                <div class="profile-stream-body">
                    <h3><a href="/question/<?= h((string) $question['slug']) ?>"><?= h((string) $question['title']) ?></a></h3>
                    <p><?= h(excerpt((string) $question['body'], 170)) ?></p>
                    <div class="profile-stream-meta">
                        <span><?= (int) ($question['answer_count'] ?? 0) ?> answers</span>
                        <span><?= (int) ($question['views'] ?? 0) ?> views</span>
                        <span><?= h($formatDate($question['created_at'] ?? 'now')) ?></span>
                        <?php $renderParticipants($participants); ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="profile-section">
    <div class="section-head">
        <h2>Last answers</h2>
        <p class="meta">Showing the most recent 10 items</p>
    </div>
    <?php if ($answers === []): ?>
        <p class="meta">No answers yet.</p>
    <?php endif; ?>
    <div class="profile-list">
        <?php foreach ($answers as $answer): ?>
            <?php $participants = $participantsByQuestion[(int) ($answer['question_id'] ?? 0)] ?? []; ?>
            <article class="profile-stream-item">
                <div class="profile-stream-side profile-stream-score">
                    <strong><?= (int) $answer['votes'] ?></strong>
                    <span>votes</span>
                </div>
                <div class="profile-stream-body">
                    <h3><a href="/question/<?= h((string) $answer['question_slug']) ?>#answer-<?= (int) $answer['id'] ?>"><?= h((string) $answer['question_title']) ?></a></h3>
                    <p><?= h(excerpt((string) $answer['body'], 170)) ?></p>
                    <div class="profile-stream-meta">
                        <span><?= (int) $answer['is_accepted'] === 1 ? 'Accepted answer' : 'Answer' ?></span>
                        <span><?= h($formatDate($answer['created_at'] ?? 'now')) ?></span>
                        <?php $renderParticipants($participants); ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
