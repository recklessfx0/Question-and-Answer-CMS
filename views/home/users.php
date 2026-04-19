<?php declare(strict_types=1);

$totalUsers = (int) (($pagination['total_items'] ?? null) ?? count($users));
$summary = is_array($summary ?? null) ? $summary : [];
$topRep = (int) ($summary['top_reputation'] ?? 0);
$totalAnswers = (int) ($summary['total_answers'] ?? 0);
$totalQuestions = (int) ($summary['total_questions'] ?? 0);

$roleLabel = static function (string $role): string {
    return match ($role) {
        'administrator' => 'Admin',
        'moderator' => 'Mod',
        default => '',
    };
};

$roleCss = static function (string $role): string {
    return match ($role) {
        'administrator' => 'user-badge-admin',
        'moderator' => 'user-badge-mod',
        default => '',
    };
};

$formatCompact = static fn (int $value): string => $value >= 1000 ? number_format($value / 1000, 1) . 'k' : (string) $value;
$initialFor = static fn (string $username): string => h(mb_strtoupper(mb_substr($username, 0, 1)));

$svgIcon = static function (string $name): string {
    return match ($name) {
        'trophy' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v4a5 5 0 0 1-10 0z"/><path d="M17 5h3v2a4 4 0 0 1-4 4"/><path d="M7 5H4v2a4 4 0 0 0 4 4"/></svg>',
        'messages' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        'question' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.09 9a3 3 0 1 1 5.82 1c0 2-3 3-3 3"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>',
        'arrow-right' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 5 7 7-7 7"/></svg>',
        default => '',
    };
};

$heroStats = [
    ['icon' => 'trophy', 'value' => $topRep, 'label' => 'Top Reputation'],
    ['icon' => 'messages', 'value' => $totalAnswers, 'label' => 'Total Answers'],
    ['icon' => 'question', 'value' => $totalQuestions, 'label' => 'Total Questions'],
];

$userStatsConfig = [
    ['key' => 'reputation', 'label' => 'Rep', 'highlight' => true],
    ['key' => 'answers', 'label' => 'Answers', 'highlight' => false],
    ['key' => 'questions', 'label' => 'Questions', 'highlight' => false],
];

$sortOptions = [
    ['value' => 'reputation', 'label' => 'Reputation'],
    ['value' => 'answers', 'label' => 'Answers'],
    ['value' => 'questions', 'label' => 'Questions'],
    ['value' => 'name', 'label' => 'Name'],
];
?>

<header class="users-hero">
    <div class="users-hero-content">
        <h1 class="users-hero-title">Community</h1>
        <p class="users-hero-subtitle">
            <span class="users-hero-count"><?= $totalUsers ?></span>
            member<?= $totalUsers !== 1 ? 's' : '' ?> contributing to the community
        </p>
    </div>
    <?php if ($totalUsers > 0): ?>
    <div class="users-hero-stats" aria-hidden="true">
        <?php foreach ($heroStats as $stat): ?>
        <div class="users-hero-stat">
            <div class="users-hero-stat-icon"><?= $svgIcon((string) $stat['icon']) ?></div>
            <div class="users-hero-stat-content">
                <strong><?= h($formatCompact((int) $stat['value'])) ?></strong>
                <span><?= h((string) $stat['label']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</header>

<div class="users-toolbar" role="toolbar" aria-label="Filter and sort users">
    <div class="users-search-wrapper">
        <svg class="users-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="8"/>
            <path d="m21 21-4.35-4.35"/>
        </svg>
        <input
            type="search"
            id="users-search"
            class="users-search-input"
            placeholder="Search members by name..."
            autocomplete="off"
            aria-label="Search members"
        >
    </div>

    <div class="users-sort" role="group" aria-label="Sort users">
        <?php foreach ($sortOptions as $index => $option): ?>
        <button
            class="users-sort-btn <?= $index === 0 ? 'is-active' : '' ?>"
            data-sort="<?= h((string) $option['value']) ?>"
            type="button"
            title="Sort by <?= h((string) $option['label']) ?>"
        >
            <?= h((string) $option['label']) ?>
        </button>
        <?php endforeach; ?>
    </div>

    <span class="users-count" id="users-count" aria-live="polite" aria-atomic="true">
        <?= $totalUsers ?> members
    </span>
</div>

<div class="users-grid" id="users-grid" data-users-directory="1" aria-label="Member list">
    <?php foreach ($users as $user): ?>
    <?php
        $username = (string) $user['username'];
        $rep = (int) $user['reputation'];
        $answers = (int) $user['answer_count'];
        $questions = (int) $user['question_count'];
        $role = (string) $user['role'];
        $badgeText = $roleLabel($role);
        $badgeCls = $roleCss($role);
    ?>
    <a
        class="user-card"
        href="/user/<?= h(rawurlencode($username)) ?>"
        data-reputation="<?= $rep ?>"
        data-answers="<?= $answers ?>"
        data-questions="<?= $questions ?>"
        aria-label="<?= h($username) ?>, <?= $rep ?> reputation"
    >
        <div class="user-card-header">
            <div class="user-card-avatar-wrapper">
                <?php if (!empty($user['avatar_path'])): ?>
                    <?= uploaded_image((string) $user['avatar_path'], 'user-card-avatar', $username . ' avatar') ?>
                <?php else: ?>
                    <div class="user-card-initials" aria-hidden="true"><?= $initialFor($username) ?></div>
                <?php endif; ?>
            </div>
            <div class="user-card-info">
                <span class="user-card-name"><?= h($username) ?></span>
                <?php if ($badgeText !== ''): ?>
                <span class="user-card-badge <?= h($badgeCls) ?>"><?= h($badgeText) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="user-card-stats">
            <?php foreach ($userStatsConfig as $stat): ?>
            <div class="user-card-stat <?= $stat['highlight'] ? 'is-highlight' : '' ?>" data-stat="<?= h((string) $stat['key']) ?>">
                <strong><?= h($stat['key'] === 'reputation' ? $formatCompact($rep) : (string) ($stat['key'] === 'answers' ? $answers : $questions)) ?></strong>
                <span><?= h((string) $stat['label']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="user-card-footer">
            <span class="user-card-action">
                <span>View Profile</span>
                <?= $svgIcon('arrow-right') ?>
            </span>
        </div>
    </a>
    <?php endforeach; ?>

    <?php if ($users === []): ?>
    <div class="users-empty">
        <div class="users-empty-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <p class="users-empty-text">No members yet.</p>
        <p class="users-empty-hint">Be the first to join our community!</p>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($pagination) && is_array($pagination) && (int) ($pagination['total_pages'] ?? 1) > 1): ?>
    <nav class="pagination" aria-label="User pages">
        <span><?= (int) ($pagination['total_items'] ?? 0) ?> members</span>
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
