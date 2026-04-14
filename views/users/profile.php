<?php declare(strict_types=1); ?>
<?php
$progress = max(0, min(100, (int) ($rank['progress'] ?? 0)));
$stats = is_array($activityStats ?? null) ? $activityStats : ['questions' => 0, 'answers' => 0, 'comments' => 0];
$formatDate = static fn (mixed $value): string => date('M j, Y', strtotime((string) $value) ?: time());
$websiteUrl = safe_external_url((string) ($profile['website_url'] ?? ''));
?>

<section class="profile-hero">
    <div class="profile-identity">
        <?php if (!empty($profile['avatar_path'])): ?>
            <?= uploaded_image((string) $profile['avatar_path'], 'avatar avatar-xl', (string) $profile['username'] . ' avatar') ?>
        <?php else: ?>
            <div class="avatar avatar-xl profile-initial" aria-hidden="true"><?= h(strtoupper(substr((string) $profile['username'], 0, 1))) ?></div>
        <?php endif; ?>
        <div class="profile-title">
            <p class="eyebrow"><?= h((string) $profile['role']) ?></p>
            <h1><?= h((string) $profile['username']) ?></h1>
            <p class="meta">Joined <?= h($formatDate($profile['created_at'] ?? 'now')) ?> &middot; <?= $profile['email_verified_at'] ? 'Email verified' : 'Email not verified' ?></p>
        </div>
    </div>
    <div class="profile-rank">
        <?php if (!empty($rank['icon_path'])): ?>
            <?= uploaded_image((string) $rank['icon_path'], 'level-icon level-icon-large', 'Level ' . (string) ($rank['level'] ?? 1) . ' ' . (string) ($rank['title'] ?? 'Member') . ' icon') ?>
        <?php endif; ?>
        <div>
            <strong>Level <?= (int) ($rank['level'] ?? 1) ?> <?= h((string) ($rank['title'] ?? 'Member')) ?></strong>
            <p class="meta"><?= (int) $profile['reputation'] ?> reputation</p>
            <progress class="profile-progress" max="100" value="<?= $progress ?>" aria-label="Level progress"><?= $progress ?>%</progress>
            <p class="meta"><?= $progress ?>% to next level</p>
        </div>
    </div>
    <?php if (!empty($profile['bio'])): ?>
        <p class="profile-bio"><?= h((string) $profile['bio']) ?></p>
    <?php endif; ?>
    <?php if (!empty($profile['expertise']) || !empty($profile['credentials']) || $websiteUrl !== ''): ?>
        <div class="profile-bio profile-authority">
            <?php if (!empty($profile['expertise'])): ?><p><strong>Expertise:</strong> <?= h((string) $profile['expertise']) ?></p><?php endif; ?>
            <?php if (!empty($profile['credentials'])): ?><p><strong>Credentials:</strong> <?= h((string) $profile['credentials']) ?></p><?php endif; ?>
            <?php if ($websiteUrl !== ''): ?><p><a href="<?= h($websiteUrl) ?>" rel="nofollow me noopener">Website</a></p><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (is_array($authUser) && (int) ($authUser['id'] ?? 0) !== (int) $profile['id']): ?>
        <?php $reportAction = '/flag/user/' . (int) $profile['id']; ?>
        <button type="button" class="button-secondary report-open" data-report-open data-report-action="<?= h($reportAction) ?>" data-report-csrf="<?= h($csrf->field($reportAction)) ?>">Report user</button>
    <?php endif; ?>
</section>

<section class="profile-stats" aria-label="Profile activity totals">
    <div><strong><?= (int) $stats['questions'] ?></strong><span>Questions</span></div>
    <div><strong><?= (int) $stats['answers'] ?></strong><span>Answers</span></div>
    <div><strong><?= (int) $stats['comments'] ?></strong><span>Clarifications</span></div>
    <div><strong><?= (int) $profile['reputation'] ?></strong><span>Reputation</span></div>
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

<div class="profile-layout">
    <section class="profile-section">
        <div class="section-head">
            <h2>Last activity</h2>
        </div>
        <?php if ($activity === []): ?>
            <p class="meta">No public activity yet.</p>
        <?php endif; ?>
        <div class="profile-timeline">
            <?php foreach ($activity as $item): ?>
                <article class="profile-activity-item">
                    <span class="activity-type"><?= h((string) $item['label']) ?></span>
                    <div>
                        <h3><a href="/question/<?= h((string) $item['slug']) ?>"><?= h((string) $item['title']) ?></a></h3>
                        <p><?= h(excerpt((string) $item['summary'], 130)) ?></p>
                        <p class="meta"><?= h($formatDate($item['created_at'] ?? 'now')) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <aside class="profile-section profile-aside">
        <h2>About</h2>
        <dl class="profile-facts">
            <div><dt>Role</dt><dd><?= h((string) $profile['role']) ?></dd></div>
            <div><dt>Level</dt><dd><?= (int) ($rank['level'] ?? 1) ?></dd></div>
            <div><dt>Reputation</dt><dd><?= (int) $profile['reputation'] ?></dd></div>
            <?php if (!empty($profile['expertise'])): ?><div><dt>Expertise</dt><dd><?= h((string) $profile['expertise']) ?></dd></div><?php endif; ?>
        </dl>
    </aside>
</div>

<section class="profile-section">
    <div class="section-head">
        <h2>Last questions</h2>
    </div>
    <?php if ($questions === []): ?>
        <p class="meta">No questions yet.</p>
    <?php endif; ?>
    <div class="profile-list">
        <?php foreach ($questions as $question): ?>
            <article class="profile-list-item">
                <div class="score"><?= (int) $question['votes'] ?></div>
                <div>
                    <h3><a href="/question/<?= h((string) $question['slug']) ?>"><?= h((string) $question['title']) ?></a></h3>
                    <p><?= h(excerpt((string) $question['body'], 150)) ?></p>
                    <p class="meta"><?= (int) ($question['answer_count'] ?? 0) ?> answers &middot; <?= (int) ($question['views'] ?? 0) ?> views &middot; <?= h($formatDate($question['created_at'] ?? 'now')) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="profile-section">
    <div class="section-head">
        <h2>Last answers</h2>
    </div>
    <?php if ($answers === []): ?>
        <p class="meta">No answers yet.</p>
    <?php endif; ?>
    <div class="profile-list">
        <?php foreach ($answers as $answer): ?>
            <article class="profile-list-item">
                <div class="score"><?= (int) $answer['votes'] ?></div>
                <div>
                    <h3><a href="/question/<?= h((string) $answer['question_slug']) ?>#answer-<?= (int) $answer['id'] ?>"><?= h((string) $answer['question_title']) ?></a></h3>
                    <p><?= h(excerpt((string) $answer['body'], 150)) ?></p>
                    <p class="meta"><?= (int) $answer['is_accepted'] === 1 ? 'Accepted answer' : 'Answer' ?> &middot; <?= h($formatDate($answer['created_at'] ?? 'now')) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
