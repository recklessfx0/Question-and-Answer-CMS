<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<section class="page-head">
    <h1>Categories</h1>
    <p class="meta">Organize questions into parent categories and subcategories.</p>
</section>
<section class="admin-panel">
    <h2>Add category</h2>
    <form method="post" action="/admin/categories" class="admin-form">
        <?= $csrf->field('/admin/categories') ?>
        <div class="form-row">
            <label>Name <input name="name" maxlength="80" required></label>
            <label>Parent
                <select name="parent_id">
                    <option value="0">Top level category</option>
                    <?php foreach ($categories as $category): ?>
                        <?php if ($category['parent_id'] === null): ?>
                            <option value="<?= (int) $category['id'] ?>"><?= h((string) $category['name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>Description <textarea name="description" rows="2" maxlength="240"></textarea></label>
        <label>SEO title <input name="seo_title" maxlength="120" placeholder="Best questions about ..."></label>
        <label>SEO description <input name="seo_description" maxlength="180" placeholder="Browse solved questions and expert answers about ..."></label>
        <label>Intro content <textarea name="intro" rows="4" maxlength="4000" placeholder="Short helpful intro for this category hub."></textarea></label>
        <button type="submit">Add category</button>
    </form>
</section>
<section class="table-wrap">
    <h2>Category tree</h2>
    <table>
        <thead><tr><th>Name</th><th>Slug</th><th>Parent</th><th>Description</th><th>SEO</th><th>Update</th><th>Delete</th></tr></thead>
        <tbody>
        <?php foreach ($categories as $category): ?>
            <tr>
                <td><?= h((string) $category['name']) ?></td>
                <td><?= h((string) $category['slug']) ?></td>
                <td><?= h((string) ($category['parent_name'] ?? '')) ?></td>
                <td><?= h((string) $category['description']) ?></td>
                <td><?= h((string) ($category['seo_title'] ?? '')) ?></td>
                <td>
                    <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/update" class="admin-table-form">
                        <?= $csrf->field('/admin/categories/' . (int) $category['id'] . '/update') ?>
                        <input name="name" value="<?= h((string) $category['name']) ?>" maxlength="80" required>
                        <select name="parent_id">
                            <option value="0">Top level</option>
                            <?php foreach ($categories as $parent): ?>
                                <?php if ($parent['parent_id'] === null && (int) $parent['id'] !== (int) $category['id']): ?>
                                    <option value="<?= (int) $parent['id'] ?>" <?= (int) ($category['parent_id'] ?? 0) === (int) $parent['id'] ? 'selected' : '' ?>><?= h((string) $parent['name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <input name="description" value="<?= h((string) $category['description']) ?>" maxlength="240">
                        <input name="seo_title" value="<?= h((string) ($category['seo_title'] ?? '')) ?>" maxlength="120" placeholder="SEO title">
                        <input name="seo_description" value="<?= h((string) ($category['seo_description'] ?? '')) ?>" maxlength="180" placeholder="SEO description">
                        <textarea name="intro" rows="4" maxlength="4000" placeholder="Intro content"><?= h((string) ($category['intro'] ?? '')) ?></textarea>
                        <button type="submit">Save</button>
                    </form>
                </td>
                <td>
                    <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/delete">
                        <?= $csrf->field('/admin/categories/' . (int) $category['id'] . '/delete') ?>
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
