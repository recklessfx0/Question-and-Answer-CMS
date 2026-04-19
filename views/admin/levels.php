<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<?php
$currentPage = max(1, (int) ($adminPage ?? 1));
$returnTo = '/admin/levels' . ($currentPage > 1 ? '?page=' . $currentPage : '');
?>
<section class="page-head admin-page-head">
    <div>
        <h1>Gamification Levels</h1>
        <p class="meta">Define level names, reputation thresholds, visibility, and icons.</p>
    </div>
</section>

<section class="admin-panel admin-create-panel">
    <div class="admin-section-head">
        <h2>Add Level</h2>
        <p class="meta">Use ascending thresholds so user progress stays predictable.</p>
    </div>
    <form method="post" action="/admin/levels" enctype="multipart/form-data" class="admin-form admin-form-enhanced">
        <?= $csrf->field('/admin/levels') ?>
        <div class="form-row">
            <label>Level Number <input name="level_number" type="number" min="1" max="100" inputmode="numeric" required></label>
            <label>Minimum Reputation <input name="min_reputation" type="number" min="0" inputmode="numeric" required></label>
        </div>
        <div class="form-row">
            <label>Title <input name="title" maxlength="80" autocomplete="off" required placeholder="Contributor"></label>
            <label>Icon <input name="icon" type="file" accept="image/png,image/jpeg,image/webp,image/avif,image/gif"></label>
        </div>
        <label>Description <input name="description" maxlength="240" autocomplete="off" placeholder="Reached a meaningful reputation milestone…"></label>
        <label class="checkbox-label"><input name="is_active" type="checkbox" value="1" checked> Active</label>
        <div class="admin-form-actions"><button type="submit">Add Level</button></div>
    </form>
</section>

<section class="admin-management">
    <div class="admin-section-head">
        <h2>Level Ladder</h2>
        <p class="meta"><?= (int) ($adminPagination['total_items'] ?? count($gamificationLevels)) ?> levels total</p>
    </div>
    <div class="admin-record-list">
        <?php foreach ($gamificationLevels as $level): ?>
            <article class="admin-record admin-record-with-media">
                <div class="admin-record-media">
                    <?php if (!empty($level['icon_path'])): ?>
                        <?= uploaded_image((string) $level['icon_path'], 'level-icon', 'Level ' . (string) $level['level_number'] . ' ' . (string) $level['title'] . ' icon') ?>
                    <?php else: ?>
                        <span class="admin-icon-fallback"><?= (int) $level['level_number'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="admin-record-body">
                    <div class="admin-record-summary">
                        <div>
                            <h3>Level <?= (int) $level['level_number'] ?> · <?= h((string) $level['title']) ?></h3>
                            <p class="meta"><?= (int) $level['min_reputation'] ?> minimum reputation</p>
                        </div>
                        <div class="admin-badges"><span><?= (int) $level['is_active'] === 1 ? 'Active' : 'Hidden' ?></span></div>
                    </div>
                    <?php if (trim((string) $level['description']) !== ''): ?>
                        <p class="admin-record-copy"><?= h((string) $level['description']) ?></p>
                    <?php endif; ?>
                    <form method="post" action="/admin/levels/<?= (int) $level['id'] ?>/update" enctype="multipart/form-data" class="admin-record-form">
                        <?= $csrf->field('/admin/levels/' . (int) $level['id'] . '/update') ?>
                        <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                        <div class="form-row">
                            <label>Level Number <input name="level_number" type="number" min="1" max="100" inputmode="numeric" value="<?= (int) $level['level_number'] ?>" required></label>
                            <label>Minimum Reputation <input name="min_reputation" type="number" min="0" inputmode="numeric" value="<?= (int) $level['min_reputation'] ?>" required></label>
                        </div>
                        <div class="form-row">
                            <label>Title <input name="title" value="<?= h((string) $level['title']) ?>" maxlength="80" autocomplete="off" required></label>
                            <label>Replace Icon <input name="icon" type="file" accept="image/png,image/jpeg,image/webp,image/avif,image/gif"></label>
                        </div>
                        <label>Description <input name="description" value="<?= h((string) $level['description']) ?>" maxlength="240" autocomplete="off"></label>
                        <label class="checkbox-label"><input name="is_active" type="checkbox" value="1" <?= (int) $level['is_active'] === 1 ? 'checked' : '' ?>> Active</label>
                        <div class="admin-record-actions"><button type="submit">Save Level</button></div>
                    </form>
                    <form method="post" action="/admin/levels/<?= (int) $level['id'] ?>/delete" class="admin-delete-form">
                        <?= $csrf->field('/admin/levels/' . (int) $level['id'] . '/delete') ?>
                        <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                        <button type="submit" class="button-danger">Delete Level</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if ($gamificationLevels === []): ?>
            <p class="admin-empty">No levels configured yet.</p>
        <?php endif; ?>
    </div>
    <?php require BASE_PATH . '/views/admin/_pagination.php'; ?>
</section>
