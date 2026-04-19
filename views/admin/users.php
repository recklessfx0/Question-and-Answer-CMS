<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<?php
$currentPage = max(1, (int) ($adminPage ?? 1));
$returnTo = '/admin/users' . ($currentPage > 1 ? '?page=' . $currentPage : '');
?>
<section class="page-head admin-page-head">
    <div>
        <h1>Users</h1>
        <p class="meta">Review member reputation and adjust roles without losing your place.</p>
    </div>
</section>

<section class="admin-management">
    <div class="admin-section-head">
        <h2>Members</h2>
        <p class="meta"><?= (int) ($adminPagination['total_items'] ?? count($users)) ?> users total</p>
    </div>
    <div class="admin-record-list">
        <?php foreach ($users as $user): ?>
            <article class="admin-record admin-user-record">
                <div class="admin-record-summary">
                    <div>
                        <h3><a href="/user/<?= rawurlencode((string) $user['username']) ?>"><?= h((string) $user['username']) ?></a></h3>
                        <p class="meta"><?= h((string) $user['email']) ?></p>
                    </div>
                    <div class="admin-badges">
                        <span><?= h((string) $user['role']) ?></span>
                        <span><?= (int) $user['reputation'] ?> reputation</span>
                    </div>
                </div>
                <form method="post" action="/admin/users/<?= (int) $user['id'] ?>/role" class="admin-record-form admin-role-form">
                    <?= $csrf->field('/admin/users/' . (int) $user['id'] . '/role') ?>
                    <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                    <label>Role
                        <select name="role">
                            <option value="user" <?= (string) $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                            <option value="moderator" <?= (string) $user['role'] === 'moderator' ? 'selected' : '' ?>>Moderator</option>
                            <option value="administrator" <?= (string) $user['role'] === 'administrator' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                    </label>
                    <div class="admin-record-actions"><button type="submit">Set Role</button></div>
                </form>
            </article>
        <?php endforeach; ?>
        <?php if ($users === []): ?>
            <p class="admin-empty">No users found.</p>
        <?php endif; ?>
    </div>
    <?php require BASE_PATH . '/views/admin/_pagination.php'; ?>
</section>
