<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<?php
$currentPage = max(1, (int) ($adminPage ?? 1));
$search = trim((string) ($userSearch ?? ''));
$query = [];
if ($search !== '') {
    $query['q'] = $search;
}
if ($currentPage > 1) {
    $query['page'] = (string) $currentPage;
}
$returnTo = '/admin/users' . ($query !== [] ? '?' . http_build_query($query) : '');
$authUserId = is_array($authUser ?? null) ? (int) ($authUser['id'] ?? 0) : 0;
?>
<section class="page-head admin-page-head">
    <div>
        <h1>Users</h1>
        <p class="meta">Search users, manage roles, ban abusive accounts, or anonymize deleted users.</p>
    </div>
</section>

<section class="admin-management">
    <div class="admin-section-head">
        <h2>Members</h2>
        <p class="meta"><?= (int) ($adminPagination['total_items'] ?? count($users)) ?> users total</p>
    </div>
    <form method="get" action="/admin/users" class="admin-search-form">
        <label>Search users
            <input name="q" value="<?= h($search) ?>" maxlength="80" autocomplete="off" placeholder="Username or email...">
        </label>
        <div class="admin-search-actions">
            <button type="submit">Search Users</button>
            <?php if ($search !== ''): ?><a class="button button-secondary" href="/admin/users">Clear</a><?php endif; ?>
        </div>
    </form>
    <div class="admin-record-list">
        <?php foreach ($users as $user): ?>
            <?php
            $isDeleted = !empty($user['deleted_at']);
            $isBanned = !empty($user['banned_at']);
            $isSelf = (int) $user['id'] === $authUserId;
            ?>
            <article class="admin-record admin-user-record">
                <div class="admin-record-summary">
                    <div>
                        <h3><a href="/user/<?= rawurlencode((string) $user['username']) ?>"><?= h((string) $user['username']) ?></a></h3>
                        <p class="meta"><?= h((string) $user['email']) ?></p>
                    </div>
                    <div class="admin-badges">
                        <span><?= h((string) $user['role']) ?></span>
                        <span><?= (int) $user['reputation'] ?> reputation</span>
                        <?php if ($isBanned): ?><span>Banned</span><?php endif; ?>
                        <?php if ($isDeleted): ?><span>Deleted</span><?php endif; ?>
                    </div>
                </div>
                <?php if (!$isDeleted): ?>
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
                    <div class="admin-user-controls">
                        <?php if (!$isSelf): ?>
                            <div class="admin-user-control-panel">
                                <div>
                                    <strong><?= $isBanned ? 'Account banned' : 'Account active' ?></strong>
                                    <p class="meta"><?= $isBanned ? 'Restore login access for this user.' : 'Block login access without deleting content.' ?></p>
                                </div>
                                <?php if ($isBanned): ?>
                                    <form method="post" action="/admin/users/<?= (int) $user['id'] ?>/unban">
                                        <?= $csrf->field('/admin/users/' . (int) $user['id'] . '/unban') ?>
                                        <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                                        <button type="submit" class="button-secondary">Unban User</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="/admin/users/<?= (int) $user['id'] ?>/ban">
                                        <?= $csrf->field('/admin/users/' . (int) $user['id'] . '/ban') ?>
                                        <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                                        <button type="submit" class="button-secondary">Ban User</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <div class="admin-user-control-panel admin-user-delete-panel">
                                <div>
                                    <strong>Delete account</strong>
                                    <p class="meta">Choose whether to keep anonymized content or hide the user's posts.</p>
                                </div>
                                <form method="post" action="/admin/users/<?= (int) $user['id'] ?>/delete" class="admin-delete-user-form">
                                    <?= $csrf->field('/admin/users/' . (int) $user['id'] . '/delete') ?>
                                    <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                                    <label>Delete Mode
                                        <select name="content_mode">
                                            <option value="keep">Keep Content & Anonymize</option>
                                            <option value="remove">Hide User Content</option>
                                        </select>
                                    </label>
                                    <label class="checkbox-label"><input name="confirm_delete" type="checkbox" value="1" required> Confirm Delete</label>
                                    <button type="submit" class="button-danger">Delete User</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <p class="meta">You cannot ban or delete your own account.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="meta">This account is deleted. Public content was <?= str_starts_with((string) $user['username'], 'deleted') ? 'anonymized' : 'updated' ?>.</p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if ($users === []): ?>
            <p class="admin-empty">No users found.</p>
        <?php endif; ?>
    </div>
    <?php require BASE_PATH . '/views/admin/_pagination.php'; ?>
</section>
