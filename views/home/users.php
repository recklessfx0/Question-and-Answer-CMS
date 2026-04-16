<?php declare(strict_types=1);

$totalUsers     = count($users);
$roleLabel      = static function (string $role): string {
    return match ($role) { 'administrator' => 'Admin', 'moderator' => 'Mod', default => '' };
};
$roleCss        = static function (string $role): string {
    return match ($role) { 'administrator' => 'badge-admin', 'moderator' => 'badge-mod', default => '' };
};
$roleIcon       = static function (string $role): string {
    return match ($role) {
        'administrator' => '<svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
        'moderator'     => '<svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>',
        default         => '',
    };
};

$totalAnswers   = array_sum(array_map(static fn ($u): int => (int) $u['answer_count'], $users));
$totalQuestions = array_sum(array_map(static fn ($u): int => (int) $u['question_count'], $users));
$topRep         = $users !== [] ? max(array_map(static fn ($u): int => (int) $u['reputation'], $users)) : 0;

$sorted3 = $users;
usort($sorted3, static fn ($a, $b): int => (int) $b['reputation'] - (int) $a['reputation']);
$podium = array_slice($sorted3, 0, 3);

$fmt = static function (int $n): string {
    if ($n >= 1_000_000) return number_format($n / 1_000_000, 1) . 'M';
    if ($n >= 1_000)     return number_format($n / 1_000,     1) . 'k';
    return (string) $n;
};
?><style>
/* ── Users page ─────────────────────────────────────────────── */

.users-hero {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: end;
    gap: 20px;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--line);
}
.users-hero-text h1 {
    margin: 0 0 4px;
    font-size: clamp(1.5rem, 4vw, 2rem);
    font-weight: 800;
    letter-spacing: -0.03em;
    line-height: 1.1;
}
.users-hero-text p { margin: 0; color: var(--muted); font-size: .875rem; }
.users-hero-stats { display: flex; gap: clamp(12px, 3vw, 28px); flex-shrink: 0; }
.users-hero-stat { text-align: right; }
.users-hero-stat strong {
    display: block;
    font-size: clamp(1.1rem, 3vw, 1.5rem);
    font-weight: 800;
    line-height: 1.05;
    letter-spacing: -0.04em;
    color: var(--accent);
}
.users-hero-stat span { font-size: .7rem; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }

/* Podium */
.users-podium {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: clamp(6px, 2vw, 16px);
    margin-bottom: 32px;
    padding: 24px 16px 0;
    background: linear-gradient(180deg, var(--soft) 0%, transparent 100%);
    border-radius: 16px;
    border: 1px solid var(--line);
    overflow: hidden;
}
.podium-slot {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: inherit;
    flex: 1 1 0;
    max-width: 180px;
    min-width: 0;
    transition: transform .2s;
}
.podium-slot:hover { transform: translateY(-3px); }
.podium-slot:focus-visible { outline: 2px solid var(--focus); border-radius: 8px; }
.podium-avatar-wrap { position: relative; margin-bottom: 10px; }
.podium-avatar, .podium-initials {
    border-radius: 50%;
    display: block;
    object-fit: cover;
    border: 3px solid var(--line);
    background: var(--soft);
}
.podium-initials {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--accent) 0%, #0e9488 100%);
    color: white;
    font-weight: 800;
    letter-spacing: -0.02em;
}
.podium-slot--1st .podium-avatar,
.podium-slot--1st .podium-initials { width: 72px; height: 72px; font-size: 1.5rem; border-color: #f59e0b; }
.podium-slot--2nd .podium-avatar,
.podium-slot--2nd .podium-initials { width: 56px; height: 56px; font-size: 1.1rem; border-color: #94a3b8; }
.podium-slot--3rd .podium-avatar,
.podium-slot--3rd .podium-initials { width: 52px; height: 52px; font-size: 1rem; border-color: #d97706; }
.podium-crown {
    position: absolute; top: -14px; left: 50%; transform: translateX(-50%);
    font-size: 1.25rem; line-height: 1;
    filter: drop-shadow(0 1px 2px rgba(0,0,0,.15));
}
.podium-rank { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; margin-bottom: 4px; color: var(--muted); }
.podium-slot--1st .podium-rank { color: #b45309; }
.podium-slot--2nd .podium-rank { color: #64748b; }
.podium-slot--3rd .podium-rank { color: #b45309; opacity: .7; }
.podium-name { font-size: .85rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; text-align: center; color: var(--ink); }
.podium-rep { font-size: .78rem; color: var(--accent); font-weight: 700; margin-top: 2px; }
.podium-plinth {
    margin-top: 10px; width: 100%; border-radius: 8px 8px 0 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.35rem; font-weight: 900; color: white; letter-spacing: -0.04em;
}
.podium-slot--1st .podium-plinth { height: 56px; background: linear-gradient(180deg, #fbbf24 0%, #f59e0b 100%); }
.podium-slot--2nd .podium-plinth { height: 42px; background: linear-gradient(180deg, #94a3b8 0%, #64748b 100%); }
.podium-slot--3rd .podium-plinth { height: 34px; background: linear-gradient(180deg, #d97706 0%, #b45309 100%); opacity: .85; }

/* Toolbar */
.users-toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 20px; }
.users-search-wrap { position: relative; flex: 1 1 180px; max-width: 340px; min-width: 140px; }
.users-search-wrap svg {
    position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
    color: var(--muted); pointer-events: none;
}
.users-search {
    padding-left: 34px !important; width: 100%; min-height: 38px;
    font-size: .875rem; border: 1px solid var(--line); border-radius: 8px;
    background: var(--panel); color: var(--ink);
}
.users-search:focus { outline: 2px solid var(--focus); outline-offset: 2px; background: var(--surface); }
.users-sort {
    display: flex; gap: 3px; background: var(--panel);
    border: 1px solid var(--line); border-radius: 8px; padding: 3px; flex-shrink: 0;
}
.users-sort-btn {
    min-height: 30px; padding: 3px 10px; border-radius: 5px; font-size: .8rem;
    font-weight: 600; border: none; cursor: pointer; background: transparent;
    color: var(--muted); transition: background .12s, color .12s; white-space: nowrap; width: auto;
}
.users-sort-btn.is-active,
.users-sort-btn:hover { background: var(--surface); color: var(--ink); box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.users-sort-btn.is-active { font-weight: 700; }
.users-role-filter { display: flex; gap: 4px; flex-shrink: 0; flex-wrap: wrap; }
.role-filter-btn {
    min-height: 30px; padding: 3px 10px; border-radius: 20px; font-size: .78rem;
    font-weight: 600; border: 1px solid var(--line); cursor: pointer;
    background: var(--panel); color: var(--muted);
    transition: background .12s, color .12s, border-color .12s; white-space: nowrap; width: auto;
}
.role-filter-btn.is-active { background: var(--accent); color: white; border-color: var(--accent); }
.role-filter-btn:hover:not(.is-active) { background: var(--soft); color: var(--ink); }
.users-count-label { margin-left: auto; font-size: .82rem; color: var(--muted); white-space: nowrap; }
.view-toggle {
    display: flex; gap: 2px; background: var(--panel);
    border: 1px solid var(--line); border-radius: 8px; padding: 3px; flex-shrink: 0;
}
.view-toggle-btn {
    min-height: 30px; width: 32px; padding: 0; border-radius: 5px; border: none;
    cursor: pointer; background: transparent; color: var(--muted);
    transition: background .12s, color .12s;
    display: flex; align-items: center; justify-content: center;
}
.view-toggle-btn.is-active { background: var(--surface); color: var(--ink); box-shadow: 0 1px 3px rgba(0,0,0,.08); }

/* Grid */
.users-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(220px, 100%), 1fr)); gap: 12px; }
.users-grid.view-list { grid-template-columns: 1fr; gap: 6px; }
.users-grid.view-list .user-card { flex-direction: row; align-items: center; padding: 12px 16px; gap: 14px; }
.users-grid.view-list .user-card-top { margin-bottom: 0; flex: 0 0 auto; }
.users-grid.view-list .user-card-divider { display: none; }
.users-grid.view-list .user-card-identity { flex: 1; min-width: 0; }
.users-grid.view-list .user-card-stats { grid-template-columns: repeat(3, 72px); gap: 6px; margin-top: 0; flex: 0 0 auto; }
.users-grid.view-list .user-card-rank { position: static; font-size: .75rem; opacity: .6; flex-shrink: 0; width: 28px; text-align: right; }
.users-grid.view-list .user-card-rep-bar { display: none; }

/* Card */
.user-card {
    display: flex; flex-direction: column;
    background: var(--surface); border: 1px solid var(--line);
    border-radius: 12px; padding: 16px 14px 12px;
    text-decoration: none; color: inherit;
    transition: border-color .15s, box-shadow .15s, transform .15s;
    position: relative; overflow: hidden;
}
.user-card::after {
    content: ''; position: absolute; inset: 0 0 auto 0; height: 3px;
    background: var(--accent); opacity: 0; transition: opacity .15s; border-radius: 12px 12px 0 0;
}
.user-card:hover { border-color: var(--accent); box-shadow: 0 4px 20px rgba(15,118,110,.1); transform: translateY(-2px); }
.user-card:hover::after { opacity: 1; }
.user-card:focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }
.user-card-rank {
    position: absolute; top: 10px; right: 10px;
    font-size: .65rem; font-weight: 800; color: var(--muted); opacity: .5; letter-spacing: .03em;
}
.user-card-top { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.user-card-avatar { flex-shrink: 0; width: 46px; height: 46px; border-radius: 10px; object-fit: cover; border: 1px solid var(--line); background: var(--soft); display: block; }
.user-card-avatar picture { display: block; width: 46px; height: 46px; border-radius: 10px; overflow: hidden; }
.user-card-avatar picture img { width: 46px; height: 46px; object-fit: cover; border-radius: 10px; border: 1px solid var(--line); }
.user-card-initials {
    flex-shrink: 0; width: 46px; height: 46px; border-radius: 10px;
    background: linear-gradient(135deg, var(--accent) 0%, #0e9488 100%);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; font-weight: 800; color: white; letter-spacing: -0.02em;
}
.user-card-identity { min-width: 0; flex: 1; }
.user-card-name { display: block; font-weight: 700; font-size: .92rem; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--ink); }
.user-card-badges { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px; }
.badge-admin, .badge-mod {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
    padding: 1px 6px; border-radius: 4px; line-height: 1.7;
}
.badge-admin { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.badge-mod   { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
.user-card-divider { height: 1px; background: var(--line); margin: 0 0 12px; border: none; }
.user-card-stats { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 5px; margin-top: auto; }
.user-card-stat { text-align: center; padding: 6px 3px 5px; background: var(--soft); border-radius: 7px; }
.user-card-stat strong { display: block; font-size: .9rem; font-weight: 750; line-height: 1.1; color: var(--ink); letter-spacing: -0.015em; }
.user-card-stat span { display: block; font-size: .63rem; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; margin-top: 2px; }
.user-card-stat:first-child strong { color: var(--accent); }
.user-card-rep-bar { height: 3px; background: var(--line); border-radius: 2px; margin: 10px 0 0; overflow: hidden; }
.user-card-rep-bar-fill { height: 100%; border-radius: 2px; background: linear-gradient(90deg, var(--accent), #0e9488); transition: width .4s ease; }

/* Empty */
.users-empty { grid-column: 1 / -1; text-align: center; padding: 56px 20px; color: var(--muted); }
.users-empty svg { display: block; margin: 0 auto 14px; opacity: .3; }
.users-empty p { margin: 0; font-size: .9rem; }

/* Responsive */
@media (max-width: 700px) {
    .users-hero { grid-template-columns: 1fr; gap: 12px; }
    .users-hero-stats { justify-content: flex-start; }
    .users-hero-stat { text-align: left; }
    .users-podium { gap: 8px; padding: 20px 10px 0; }
    .users-count-label { display: none; }
    .view-toggle { display: none; }
    .users-grid { grid-template-columns: repeat(auto-fill, minmax(min(160px, 100%), 1fr)); gap: 10px; }
    .user-card { padding: 12px 11px 10px; }
    .user-card-avatar, .user-card-initials { width: 40px; height: 40px; font-size: 1rem; border-radius: 9px; }
    .user-card-avatar picture, .user-card-avatar picture img { width: 40px; height: 40px; }
    .user-card-stat strong { font-size: .82rem; }
    .users-sort { flex-wrap: nowrap; overflow-x: auto; }
}
@media (max-width: 480px) {
    .users-toolbar { gap: 6px; }
    .users-search-wrap { flex-basis: 100%; max-width: 100%; }
    .podium-slot--2nd .podium-avatar,
    .podium-slot--2nd .podium-initials { width: 48px; height: 48px; }
    .podium-slot--3rd .podium-avatar,
    .podium-slot--3rd .podium-initials { width: 44px; height: 44px; }
}
@media (max-width: 360px) {
    .users-grid { grid-template-columns: 1fr; }
    .users-grid.view-list .user-card-stats { grid-template-columns: repeat(3, 60px); }
}
@media (prefers-reduced-motion: reduce) {
    .user-card, .podium-slot { transition: none; }
    .user-card-rep-bar-fill { transition: none; }
}
</style>

<header class="users-hero">
    <div class="users-hero-text">
        <h1>Community</h1>
        <p><?= $totalUsers ?> member<?= $totalUsers !== 1 ? 's' : '' ?></p>
    </div>
    <?php if ($totalUsers > 0): ?>
    <div class="users-hero-stats" aria-hidden="true">
        <div class="users-hero-stat">
            <strong><?= $fmt($topRep) ?></strong>
            <span>Top rep</span>
        </div>
        <div class="users-hero-stat">
            <strong><?= $fmt($totalAnswers) ?></strong>
            <span>Answers</span>
        </div>
        <div class="users-hero-stat">
            <strong><?= $fmt($totalQuestions) ?></strong>
            <span>Questions</span>
        </div>
    </div>
    <?php endif; ?>
</header>

<?php if (count($podium) >= 2): ?>
<div class="users-podium" aria-label="Top members by reputation" role="list">
    <?php
    $podiumOrder = [];
    if (isset($podium[1])) $podiumOrder[] = ['rank' => 2, 'slot' => '2nd', 'data' => $podium[1]];
    if (isset($podium[0])) $podiumOrder[] = ['rank' => 1, 'slot' => '1st', 'data' => $podium[0]];
    if (isset($podium[2])) $podiumOrder[] = ['rank' => 3, 'slot' => '3rd', 'data' => $podium[2]];
    foreach ($podiumOrder as $p):
        $u         = $p['data'];
        $uname     = h((string) $u['username']);
        $urep      = (int) $u['reputation'];
        $initial   = h(mb_strtoupper(mb_substr((string) $u['username'], 0, 1)));
        $rankLabel = match ($p['rank']) { 1 => '1st', 2 => '2nd', 3 => '3rd' };
    ?>
    <a class="podium-slot podium-slot--<?= $p['slot'] ?>"
       href="/user/<?= h(rawurlencode((string) $u['username'])) ?>"
       aria-label="<?= $uname ?>, rank <?= $p['rank'] ?>, <?= $urep ?> reputation"
       role="listitem">
        <div class="podium-rank"><?= $rankLabel ?></div>
        <div class="podium-avatar-wrap">
            <?php if ($p['rank'] === 1): ?>
                <span class="podium-crown" aria-hidden="true">👑</span>
            <?php endif; ?>
            <?php if (!empty($u['avatar_path'])): ?>
                <?= uploaded_image((string) $u['avatar_path'], 'podium-avatar', $uname . ' avatar') ?>
            <?php else: ?>
                <div class="podium-initials" aria-hidden="true"><?= $initial ?></div>
            <?php endif; ?>
        </div>
        <div class="podium-name"><?= $uname ?></div>
        <div class="podium-rep"><?= $fmt($urep) ?> rep</div>
        <div class="podium-plinth" aria-hidden="true"><?= $p['rank'] ?></div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="users-toolbar" role="toolbar" aria-label="Filter and sort members">
    <div class="users-search-wrap">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="search" id="users-search" class="users-search" placeholder="Search members&hellip;" autocomplete="off" aria-label="Search members">
    </div>

    <div class="users-sort" role="group" aria-label="Sort by">
        <button class="users-sort-btn is-active" data-sort="reputation" type="button">Rep</button>
        <button class="users-sort-btn" data-sort="answers"   type="button">Answers</button>
        <button class="users-sort-btn" data-sort="questions" type="button">Questions</button>
        <button class="users-sort-btn" data-sort="name"      type="button">Name</button>
    </div>

    <div class="users-role-filter" role="group" aria-label="Filter by role">
        <button class="role-filter-btn is-active" data-role=""              type="button">All</button>
        <button class="role-filter-btn"           data-role="administrator" type="button">Admins</button>
        <button class="role-filter-btn"           data-role="moderator"     type="button">Mods</button>
    </div>

    <div class="view-toggle" role="group" aria-label="View mode">
        <button class="view-toggle-btn is-active" data-view="grid" type="button" title="Grid view" aria-pressed="true">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        </button>
        <button class="view-toggle-btn" data-view="list" type="button" title="List view" aria-pressed="false">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        </button>
    </div>

    <span class="users-count-label" id="users-count-label" aria-live="polite" aria-atomic="true"><?= $totalUsers ?> members</span>
</div>

<div class="users-grid" id="users-grid" aria-label="Member list">
    <?php
    $rank = 0;
    foreach ($users as $user):
        $rank++;
        $username   = (string) $user['username'];
        $rep        = (int)    $user['reputation'];
        $answers    = (int)    $user['answer_count'];
        $questions  = (int)    $user['question_count'];
        $role       = (string) $user['role'];
        $initial    = h(mb_strtoupper(mb_substr($username, 0, 1)));
        $badgeText  = $roleLabel($role);
        $badgeCls   = $roleCss($role);
        $badgeIcon  = $roleIcon($role);
        $repDisplay = $fmt($rep);
        $repPct     = $topRep > 0 ? min(100, (int) round($rep / $topRep * 100)) : 0;
    ?>
    <a
        class="user-card"
        href="/user/<?= h(rawurlencode($username)) ?>"
        data-username="<?= h(mb_strtolower($username)) ?>"
        data-reputation="<?= $rep ?>"
        data-answers="<?= $answers ?>"
        data-questions="<?= $questions ?>"
        data-role="<?= h($role) ?>"
        aria-label="<?= h($username) ?>, <?= $rep ?> reputation"
    >
        <span class="user-card-rank" aria-hidden="true">#<?= $rank ?></span>
        <div class="user-card-top">
            <?php if (!empty($user['avatar_path'])): ?>
                <?= uploaded_image((string) $user['avatar_path'], 'user-card-avatar', h($username) . ' avatar') ?>
            <?php else: ?>
                <div class="user-card-initials" aria-hidden="true"><?= $initial ?></div>
            <?php endif; ?>
            <div class="user-card-identity">
                <span class="user-card-name"><?= h($username) ?></span>
                <?php if ($badgeText !== ''): ?>
                <div class="user-card-badges">
                    <span class="<?= h($badgeCls) ?>"><?= $badgeIcon ?><?= h($badgeText) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <hr class="user-card-divider">
        <div class="user-card-stats">
            <div class="user-card-stat">
                <strong><?= h($repDisplay) ?></strong>
                <span>Rep</span>
            </div>
            <div class="user-card-stat">
                <strong><?= $answers ?></strong>
                <span>Answers</span>
            </div>
            <div class="user-card-stat">
                <strong><?= $questions ?></strong>
                <span>Questions</span>
            </div>
        </div>
        <?php if ($topRep > 0): ?>
        <div class="user-card-rep-bar" aria-hidden="true">
            <div class="user-card-rep-bar-fill" style="width:<?= $repPct ?>%"></div>
        </div>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
    <?php if ($users === []): ?>
    <div class="users-empty">
        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <p>No members yet.</p>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    'use strict';
    var grid     = document.getElementById('users-grid');
    var search   = document.getElementById('users-search');
    var label    = document.getElementById('users-count-label');
    var sortBtns = document.querySelectorAll('.users-sort-btn');
    var roleBtns = document.querySelectorAll('.role-filter-btn');
    var viewBtns = document.querySelectorAll('.view-toggle-btn');
    if (!grid || !search) return;

    var cards        = Array.from(grid.querySelectorAll('.user-card'));
    var currentSort  = 'reputation';
    var currentQuery = '';
    var currentRole  = '';

    function sorted() {
        return cards.slice().sort(function (a, b) {
            if (currentSort === 'name') {
                return (a.dataset.username || '').localeCompare(b.dataset.username || '');
            }
            var key = currentSort === 'answers' ? 'answers'
                    : currentSort === 'questions' ? 'questions'
                    : 'reputation';
            return Number(b.dataset[key] || 0) - Number(a.dataset[key] || 0);
        });
    }

    function render() {
        var q       = currentQuery.trim().toLowerCase();
        var emptyEl = grid.querySelector('.users-empty');
        cards.forEach(function (c) { c.remove(); });
        if (emptyEl) emptyEl.remove();

        var visible = 0;
        var rank    = 0;
        sorted().forEach(function (card) {
            var matchQuery = q === '' || (card.dataset.username || '').includes(q);
            var matchRole  = currentRole === '' || card.dataset.role === currentRole;
            var show       = matchQuery && matchRole;
            card.hidden = !show;
            if (show) {
                rank++;
                var rankEl = card.querySelector('.user-card-rank');
                if (rankEl) rankEl.textContent = '#' + rank;
                visible++;
            }
            grid.appendChild(card);
        });

        if (emptyEl) { emptyEl.hidden = visible > 0; grid.appendChild(emptyEl); }
        if (label) {
            label.textContent = visible === cards.length
                ? cards.length + ' member' + (cards.length !== 1 ? 's' : '')
                : visible + ' of ' + cards.length + ' members';
        }
    }

    search.addEventListener('input', function () { currentQuery = search.value; render(); });

    sortBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            sortBtns.forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            currentSort = btn.dataset.sort || 'reputation';
            render();
        });
    });

    roleBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            roleBtns.forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            currentRole = btn.dataset.role || '';
            render();
        });
    });

    viewBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            viewBtns.forEach(function (b) {
                b.classList.remove('is-active');
                b.setAttribute('aria-pressed', 'false');
            });
            btn.classList.add('is-active');
            btn.setAttribute('aria-pressed', 'true');
            grid.classList.toggle('view-list', btn.dataset.view === 'list');
        });
    });
}());
</script>
