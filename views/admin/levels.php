<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<section class="page-head">
    <h1>Gamification levels</h1>
    <p class="meta">Define reputation thresholds, titles, active status, and icons.</p>
</section>
<section class="admin-panel">
    <h2>Add level</h2>
    <form method="post" action="/admin/levels" enctype="multipart/form-data" class="admin-form">
        <?= $csrf->field('/admin/levels') ?>
        <div class="form-row">
            <label>Level <input name="level_number" type="number" min="1" max="100" required></label>
            <label>Minimum reputation <input name="min_reputation" type="number" min="0" required></label>
        </div>
        <label>Title <input name="title" maxlength="80" required placeholder="Contributor"></label>
        <label>Description <input name="description" maxlength="240" placeholder="Reached a meaningful reputation milestone."></label>
        <label>Icon <input name="icon" type="file" accept="image/png,image/jpeg,image/webp,image/avif,image/gif"></label>
        <label class="checkbox-label"><input name="is_active" type="checkbox" value="1" checked> Active</label>
        <button type="submit">Add level</button>
    </form>
</section>
<section class="table-wrap">
    <h2>Level ladder</h2>
    <table>
        <thead><tr><th>Icon</th><th>Level</th><th>Title</th><th>Minimum reputation</th><th>Description</th><th>Status</th><th>Update</th><th>Delete</th></tr></thead>
        <tbody>
        <?php foreach ($gamificationLevels as $level): ?>
            <tr>
                <td>
                    <?php if (!empty($level['icon_path'])): ?>
                        <?= uploaded_image((string) $level['icon_path'], 'level-icon', 'Level ' . (string) $level['level_number'] . ' ' . (string) $level['title'] . ' icon') ?>
                    <?php endif; ?>
                </td>
                <td><?= (int) $level['level_number'] ?></td>
                <td><?= h((string) $level['title']) ?></td>
                <td><?= (int) $level['min_reputation'] ?></td>
                <td><?= h((string) $level['description']) ?></td>
                <td><?= (int) $level['is_active'] === 1 ? 'Active' : 'Hidden' ?></td>
                <td>
                    <form method="post" action="/admin/levels/<?= (int) $level['id'] ?>/update" enctype="multipart/form-data" class="admin-table-form">
                        <?= $csrf->field('/admin/levels/' . (int) $level['id'] . '/update') ?>
                        <input name="level_number" type="number" min="1" max="100" value="<?= (int) $level['level_number'] ?>" required>
                        <input name="min_reputation" type="number" min="0" value="<?= (int) $level['min_reputation'] ?>" required>
                        <input name="title" value="<?= h((string) $level['title']) ?>" maxlength="80" required>
                        <input name="description" value="<?= h((string) $level['description']) ?>" maxlength="240">
                        <input name="icon" type="file" accept="image/png,image/jpeg,image/webp,image/avif,image/gif">
                        <label class="checkbox-label"><input name="is_active" type="checkbox" value="1" <?= (int) $level['is_active'] === 1 ? 'checked' : '' ?>> Active</label>
                        <button type="submit">Save</button>
                    </form>
                </td>
                <td>
                    <form method="post" action="/admin/levels/<?= (int) $level['id'] ?>/delete">
                        <?= $csrf->field('/admin/levels/' . (int) $level['id'] . '/delete') ?>
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
