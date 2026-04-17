<?php declare(strict_types=1);

$totalUsers     = count($users);
$roleLabel      = static function (string $role): string {
    return match ($role) { 'administrator' => 'Admin', 'moderator' => 'Mod', default => '' };
};
$roleCss        = static function (string $role): string {
    return match ($role) { 'administrator' => 'user-badge-admin', 'moderator' => 'user-badge-mod', default => '' };
};
$totalAnswers   = array_sum(array_map(static fn ($u): int => (int) $u['answer_count'], $users));
$totalQuestions = array_sum(array_map(static fn ($u): int => (int) $u['question_count'], $users));
$topRep         = $users !== [] ? max(array_map(static fn ($u): int => (int) $u['reputation'], $users)) : 0;

// Configuration for stats display - easily customizable
$userStatsConfig = [
    ['key' => 'reputation', 'label' => 'Rep', 'icon' => 'star', 'highlight' => true],
    ['key' => 'answers', 'label' => 'Answers', 'icon' => 'message-circle', 'highlight' => false],
    ['key' => 'questions', 'label' => 'Questions', 'icon' => 'help-circle', 'highlight' => false],
];

// Sort options - easily customizable
$sortOptions = [
    ['value' => 'reputation', 'label' => 'Reputation', 'icon' => 'trophy'],
    ['value' => 'answers', 'label' => 'Answers', 'icon' => 'message-circle'],
    ['value' => 'questions', 'label' => 'Questions', 'icon' => 'help-circle'],
    ['value' => 'name', 'label' => 'Name', 'icon' => 'user'],
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
        <div class="users-hero-stat">
            <div class="users-hero-stat-icon">🏆</div>
            <div class="users-hero-stat-content">
                <strong><?= $topRep >= 1000 ? number_format($topRep / 1000, 1) . 'k' : $topRep ?></strong>
                <span>Top Reputation</span>
            </div>
        </div>
        <div class="users-hero-stat">
            <div class="users-hero-stat-icon">💬</div>
            <div class="users-hero-stat-content">
                <strong><?= $totalAnswers >= 1000 ? number_format($totalAnswers / 1000, 1) . 'k' : $totalAnswers ?></strong>
                <span>Total Answers</span>
            </div>
        </div>
        <div class="users-hero-stat">
            <div class="users-hero-stat-icon">❓</div>
            <div class="users-hero-stat-content">
                <strong><?= $totalQuestions >= 1000 ? number_format($totalQuestions / 1000, 1) . 'k' : $totalQuestions ?></strong>
                <span>Total Questions</span>
            </div>
        </div>
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
            data-sort="<?= $option['value'] ?>"
            type="button"
            title="Sort by <?= h($option['label']) ?>"
        >
            <?= h($option['label']) ?>
        </button>
        <?php endforeach; ?>
    </div>
    
    <span class="users-count" id="users-count" aria-live="polite" aria-atomic="true">
        <?= $totalUsers ?> members
    </span>
</div>

<div class="users-grid" id="users-grid" aria-label="Member list">
    <?php foreach ($users as $user): ?>
    <?php
        $username   = (string) $user['username'];
        $rep        = (int)    $user['reputation'];
        $answers    = (int)    $user['answer_count'];
        $questions  = (int)    $user['question_count'];
        $role       = (string) $user['role'];
        $initial    = h(mb_strtoupper(mb_substr($username, 0, 1)));
        $badgeText  = $roleLabel($role);
        $badgeCls   = $roleCss($role);
        $repDisplay = $rep >= 1000 ? number_format($rep / 1000, 1) . 'k' : (string) $rep;
    ?>
    <a
        class="user-card"
        href="/user/<?= h(rawurlencode($username)) ?>"
        data-username="<?= h(mb_strtolower($username)) ?>"
        data-reputation="<?= $rep ?>"
        data-answers="<?= $answers ?>"
        data-questions="<?= $questions ?>"
        aria-label="<?= h($username) ?>, <?= $rep ?> reputation"
    >
        <div class="user-card-header">
            <div class="user-card-avatar-wrapper">
                <?php if (!empty($user['avatar_path'])): ?>
                    <?= uploaded_image((string) $user['avatar_path'], 'user-card-avatar', h($username) . ' avatar') ?>
                <?php else: ?>
                    <div class="user-card-initials" aria-hidden="true"><?= $initial ?></div>
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
            <div class="user-card-stat <?= $stat['highlight'] ? 'is-highlight' : '' ?>" data-stat="<?= $stat['key'] ?>">
                <strong><?= $stat['key'] === 'reputation' ? h($repDisplay) : ($stat['key'] === 'answers' ? $answers : $questions) ?></strong>
                <span><?= h($stat['label']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="user-card-footer">
            <span class="user-card-action">View Profile →</span>
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

<script>
(function () {
    const grid = document.getElementById('users-grid');
    const search = document.getElementById('users-search');
    const count = document.getElementById('users-count');
    const sortBtns = document.querySelectorAll('.users-sort-btn');
    
    if (!grid || !search) return;

    const cards = Array.from(grid.querySelectorAll('.user-card'));
    let currentSort = 'reputation';
    let currentQuery = '';

    function sortCards() {
        return [...cards].sort(function (a, b) {
            if (currentSort === 'name') {
                return (a.dataset.username || '').localeCompare(b.dataset.username || '');
            }
            const key = currentSort === 'answers' ? 'answers' : currentSort === 'questions' ? 'questions' : 'reputation';
            return Number(b.dataset[key] || 0) - Number(a.dataset[key] || 0);
        });
    }

    function render() {
        const q = currentQuery.trim().toLowerCase();
        const emptyEl = grid.querySelector('.users-empty');
        
        cards.forEach(c => c.remove());
        if (emptyEl) emptyEl.remove();

        let visible = 0;
        sortCards().forEach(function (card) {
            const match = q === '' || (card.dataset.username || '').includes(q);
            card.hidden = !match;
            if (match) visible++;
            grid.appendChild(card);
        });
        
        if (emptyEl) {
            emptyEl.hidden = visible > 0;
            grid.appendChild(emptyEl);
        }
        
        if (count) {
            count.textContent = visible === cards.length
                ? cards.length + ' member' + (cards.length !== 1 ? 's' : '')
                : visible + ' of ' + cards.length + ' members';
        }
    }

    search.addEventListener('input', function () {
        currentQuery = search.value;
        render();
    });

    sortBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            sortBtns.forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            currentSort = btn.dataset.sort || 'reputation';
            render();
        });
    });
}());
</script>
