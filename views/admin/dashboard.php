<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<section class="page-head">
    <h1>Admin overview</h1>
    <p><?= (int) $stats['questions'] ?> questions &middot; <?= (int) $stats['answers'] ?> answers &middot; <?= (int) $stats['users'] ?> users</p>
</section>
<section class="admin-grid" aria-label="Admin areas">
    <a class="admin-card" href="/admin/settings">
        <strong>Settings</strong>
        <span>Site details, reCAPTCHA, and Google sign-in.</span>
    </a>
    <a class="admin-card" href="/admin/categories">
        <strong>Categories</strong>
        <span><?= count($categories) ?> categories and subcategories.</span>
    </a>
    <a class="admin-card" href="/admin/levels">
        <strong>Levels</strong>
        <span><?= count($gamificationLevels) ?> gamification levels.</span>
    </a>
    <a class="admin-card" href="/admin/users">
        <strong>Users</strong>
        <span>Roles and reputation.</span>
    </a>
    <a class="admin-card" href="/admin/reports">
        <strong>Reports</strong>
        <span><?= count($reports) ?> open user reports.</span>
    </a>
</section>
