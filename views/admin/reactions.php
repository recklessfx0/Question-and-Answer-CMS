<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<section class="page-head">
    <h1>Reactions</h1>
    <p class="meta">Create reaction buttons, upload icons, and connect them to reputation progress.</p>
</section>
<section class="admin-panel">
    <h2>Add reaction</h2>
    <form method="post" action="/admin/reactions" enctype="multipart/form-data" class="admin-form">
        <?= $csrf->field('/admin/reactions') ?>
        <div class="form-row">
            <label>Name <input name="name" maxlength="60" required placeholder="Useful"></label>
            <label>Reputation points <input name="reputation_delta" type="number" min="-10" max="25" value="1" required></label>
        </div>
        <div class="form-row">
            <label>Sort order <input name="sort_order" type="number" min="0" value="10"></label>
            <label>Icon <input name="icon" type="file" accept="image/png,image/jpeg,image/webp,image/avif,image/gif"></label>
        </div>
        <label class="checkbox-label"><input name="is_active" type="checkbox" value="1" checked> Active</label>
        <button type="submit">Add reaction</button>
    </form>
</section>
<section class="table-wrap">
    <h2>Reaction types</h2>
    <table>
        <thead><tr><th>Icon</th><th>Name</th><th>Slug</th><th>Points</th><th>Sort</th><th>Status</th><th>Update</th><th>Delete</th></tr></thead>
        <tbody>
        <?php foreach ($reactions as $reaction): ?>
            <tr>
                <td>
                    <?php if (!empty($reaction['icon_path'])): ?>
                        <?= uploaded_image((string) $reaction['icon_path'], 'reaction-icon-admin', (string) $reaction['name'] . ' reaction icon') ?>
                    <?php else: ?>
                        <span class="reaction-icon-fallback"><?= h(mb_strtoupper(mb_substr((string) $reaction['name'], 0, 1))) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= h((string) $reaction['name']) ?></td>
                <td><?= h((string) $reaction['slug']) ?></td>
                <td><?= (int) $reaction['reputation_delta'] ?></td>
                <td><?= (int) $reaction['sort_order'] ?></td>
                <td><?= (int) $reaction['is_active'] === 1 ? 'Active' : 'Hidden' ?></td>
                <td>
                    <form method="post" action="/admin/reactions/<?= (int) $reaction['id'] ?>/update" enctype="multipart/form-data" class="admin-table-form">
                        <?= $csrf->field('/admin/reactions/' . (int) $reaction['id'] . '/update') ?>
                        <input name="name" value="<?= h((string) $reaction['name']) ?>" maxlength="60" required>
                        <input name="reputation_delta" type="number" min="-10" max="25" value="<?= (int) $reaction['reputation_delta'] ?>" required>
                        <input name="sort_order" type="number" min="0" value="<?= (int) $reaction['sort_order'] ?>">
                        <input name="icon" type="file" accept="image/png,image/jpeg,image/webp,image/avif,image/gif">
                        <label class="checkbox-label"><input name="is_active" type="checkbox" value="1" <?= (int) $reaction['is_active'] === 1 ? 'checked' : '' ?>> Active</label>
                        <button type="submit">Save</button>
                    </form>
                </td>
                <td>
                    <form method="post" action="/admin/reactions/<?= (int) $reaction['id'] ?>/delete">
                        <?= $csrf->field('/admin/reactions/' . (int) $reaction['id'] . '/delete') ?>
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($reactions === []): ?>
            <tr><td colspan="8">No reactions configured yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>
