<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<?php
$allCategories = is_array($allCategories ?? null) ? $allCategories : $categories;
$currentPage = max(1, (int) ($adminPage ?? 1));
$returnTo = '/admin/categories' . ($currentPage > 1 ? '?page=' . $currentPage : '');
?>
<section class="page-head admin-page-head">
    <div>
        <h1>Categories</h1>
        <p class="meta">Organize question hubs, parent categories, SEO snippets, and intro copy.</p>
    </div>
</section>

<section class="admin-panel admin-create-panel">
    <div class="admin-section-head">
        <h2>Add Category</h2>
        <p class="meta">Create a new top-level category or place it under an existing parent.</p>
    </div>
    <form method="post" action="/admin/categories" class="admin-form admin-form-enhanced">
        <?= $csrf->field('/admin/categories') ?>
        <div class="form-row">
            <label>Name <input name="name" maxlength="80" autocomplete="off" required placeholder="Growing"></label>
            <label>Parent
                <select name="parent_id">
                    <option value="0">Top Level Category</option>
                    <?php foreach ($allCategories as $category): ?>
                        <?php if ($category['parent_id'] === null): ?>
                            <option value="<?= (int) $category['id'] ?>"><?= h((string) $category['name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>Description <textarea name="description" rows="2" maxlength="240" placeholder="Short category summary…"></textarea></label>
        <div class="form-row">
            <label>SEO Title <input name="seo_title" maxlength="120" autocomplete="off" placeholder="Best questions about growing…"></label>
            <label>SEO Description <input name="seo_description" maxlength="180" autocomplete="off" placeholder="Browse solved questions and expert answers…"></label>
        </div>
        <label>Intro Content <textarea name="intro" rows="4" maxlength="4000" placeholder="Helpful intro for this category hub…"></textarea></label>
        <div class="admin-form-actions"><button type="submit">Add Category</button></div>
    </form>
</section>

<section class="admin-management">
    <div class="admin-section-head">
        <h2>Category Tree</h2>
        <p class="meta"><?= (int) ($adminPagination['total_items'] ?? count($categories)) ?> categories total</p>
    </div>
    <div class="admin-record-list">
        <?php foreach ($categories as $category): ?>
            <article class="admin-record">
                <div class="admin-record-summary">
                    <div>
                        <h3><?= h((string) $category['name']) ?></h3>
                        <p class="meta">/category/<?= h((string) $category['slug']) ?></p>
                    </div>
                    <div class="admin-badges">
                        <span><?= $category['parent_name'] ? h((string) $category['parent_name']) : 'Top Level' ?></span>
                        <span><?= trim((string) ($category['seo_title'] ?? '')) !== '' ? 'SEO Ready' : 'Needs SEO' ?></span>
                    </div>
                </div>
                <?php if (trim((string) $category['description']) !== ''): ?>
                    <p class="admin-record-copy"><?= h((string) $category['description']) ?></p>
                <?php endif; ?>
                <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/update" class="admin-record-form">
                    <?= $csrf->field('/admin/categories/' . (int) $category['id'] . '/update') ?>
                    <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                    <div class="form-row">
                        <label>Name <input name="name" value="<?= h((string) $category['name']) ?>" maxlength="80" autocomplete="off" required></label>
                        <label>Parent
                            <select name="parent_id">
                                <option value="0">Top Level</option>
                                <?php foreach ($allCategories as $parent): ?>
                                    <?php if ($parent['parent_id'] === null && (int) $parent['id'] !== (int) $category['id']): ?>
                                        <option value="<?= (int) $parent['id'] ?>" <?= (int) ($category['parent_id'] ?? 0) === (int) $parent['id'] ? 'selected' : '' ?>><?= h((string) $parent['name']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <label>Description <input name="description" value="<?= h((string) $category['description']) ?>" maxlength="240" autocomplete="off" placeholder="Short category summary…"></label>
                    <div class="form-row">
                        <label>SEO Title <input name="seo_title" value="<?= h((string) ($category['seo_title'] ?? '')) ?>" maxlength="120" autocomplete="off" placeholder="SEO title…"></label>
                        <label>SEO Description <input name="seo_description" value="<?= h((string) ($category['seo_description'] ?? '')) ?>" maxlength="180" autocomplete="off" placeholder="SEO description…"></label>
                    </div>
                    <label>Intro Content <textarea name="intro" rows="4" maxlength="4000" placeholder="Intro content…"><?= h((string) ($category['intro'] ?? '')) ?></textarea></label>
                    <div class="admin-record-actions">
                        <button type="submit">Save Category</button>
                    </div>
                </form>
                <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/delete" class="admin-delete-form">
                    <?= $csrf->field('/admin/categories/' . (int) $category['id'] . '/delete') ?>
                    <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                    <button type="submit" class="button-danger">Delete Category</button>
                </form>
            </article>
        <?php endforeach; ?>
        <?php if ($categories === []): ?>
            <p class="admin-empty">No categories yet.</p>
        <?php endif; ?>
    </div>
    <?php require BASE_PATH . '/views/admin/_pagination.php'; ?>
</section>
