<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<?php
$currentPage = max(1, (int) ($adminPage ?? 1));
$returnTo = '/admin/reactions' . ($currentPage > 1 ? '?page=' . $currentPage : '');
?>
<section class="page-head admin-page-head">
    <div>
        <h1>Reactions</h1>
        <p class="meta">Create lightweight feedback buttons and connect each reaction to reputation points.</p>
    </div>
</section>

<section class="admin-panel admin-create-panel">
    <div class="admin-section-head">
        <h2>Add Reaction</h2>
        <p class="meta">Keep reaction names short so they fit beside answers and comments.</p>
    </div>
    <form method="post" action="/admin/reactions" enctype="multipart/form-data" class="admin-form admin-form-enhanced">
        <?= $csrf->field('/admin/reactions') ?>
        <div class="form-row">
            <label>Name <input name="name" maxlength="60" autocomplete="off" required placeholder="Useful"></label>
            <label>Reputation Points <input name="reputation_delta" type="number" min="-10" max="25" inputmode="numeric" value="1" required></label>
        </div>
        <div class="form-row">
            <label>Sort Order <input name="sort_order" type="number" min="0" inputmode="numeric" value="10"></label>
            <label>Icon <input name="icon" type="file" accept="image/png,image/jpeg,image/webp,image/avif,image/gif"></label>
        </div>
        <label class="checkbox-label"><input name="is_active" type="checkbox" value="1" checked> Active</label>
        <div class="admin-form-actions"><button type="submit">Add Reaction</button></div>
    </form>
</section>

<section class="admin-management">
    <div class="admin-section-head">
        <h2>Reaction Types</h2>
        <p class="meta"><?= (int) ($adminPagination['total_items'] ?? count($reactions)) ?> reactions total</p>
    </div>
    <div class="admin-record-list">
        <?php foreach ($reactions as $reaction): ?>
            <article class="admin-record admin-record-with-media">
                <div class="admin-record-media">
                    <?php if (!empty($reaction['icon_path'])): ?>
                        <?= uploaded_image((string) $reaction['icon_path'], 'reaction-icon-admin', (string) $reaction['name'] . ' reaction icon') ?>
                    <?php else: ?>
                        <span class="admin-icon-fallback"><?= h(mb_strtoupper(mb_substr((string) $reaction['name'], 0, 1))) ?></span>
                    <?php endif; ?>
                </div>
                <div class="admin-record-body">
                    <div class="admin-record-summary">
                        <div>
                            <h3><?= h((string) $reaction['name']) ?></h3>
                            <p class="meta"><?= h((string) $reaction['slug']) ?> · sort <?= (int) $reaction['sort_order'] ?></p>
                        </div>
                        <div class="admin-badges">
                            <span><?= (int) $reaction['reputation_delta'] ?> points</span>
                            <span><?= (int) $reaction['is_active'] === 1 ? 'Active' : 'Hidden' ?></span>
                        </div>
                    </div>
                    <form method="post" action="/admin/reactions/<?= (int) $reaction['id'] ?>/update" enctype="multipart/form-data" class="admin-record-form">
                        <?= $csrf->field('/admin/reactions/' . (int) $reaction['id'] . '/update') ?>
                        <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                        <div class="form-row">
                            <label>Name <input name="name" value="<?= h((string) $reaction['name']) ?>" maxlength="60" autocomplete="off" required></label>
                            <label>Reputation Points <input name="reputation_delta" type="number" min="-10" max="25" inputmode="numeric" value="<?= (int) $reaction['reputation_delta'] ?>" required></label>
                        </div>
                        <div class="form-row">
                            <label>Sort Order <input name="sort_order" type="number" min="0" inputmode="numeric" value="<?= (int) $reaction['sort_order'] ?>"></label>
                            <label>Replace Icon <input name="icon" type="file" accept="image/png,image/jpeg,image/webp,image/avif,image/gif"></label>
                        </div>
                        <label class="checkbox-label"><input name="is_active" type="checkbox" value="1" <?= (int) $reaction['is_active'] === 1 ? 'checked' : '' ?>> Active</label>
                        <div class="admin-record-actions"><button type="submit">Save Reaction</button></div>
                    </form>
                    <form method="post" action="/admin/reactions/<?= (int) $reaction['id'] ?>/delete" class="admin-delete-form">
                        <?= $csrf->field('/admin/reactions/' . (int) $reaction['id'] . '/delete') ?>
                        <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                        <button type="submit" class="button-danger">Delete Reaction</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if ($reactions === []): ?>
            <p class="admin-empty">No reactions configured yet.</p>
        <?php endif; ?>
    </div>
    <?php require BASE_PATH . '/views/admin/_pagination.php'; ?>
</section>
