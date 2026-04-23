<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<?php
$tagSeoWarnings = is_array($tagSeoWarnings ?? null) ? $tagSeoWarnings : [];
$currentPage = max(1, (int) ($adminPage ?? 1));
$returnTo = '/admin/tags' . ($currentPage > 1 ? '?page=' . $currentPage : '');
?>
<section class="page-head admin-page-head">
    <div>
        <h1>Tags</h1>
        <p class="meta">Tune tag hubs with helpful descriptions, intro copy, and search snippets.</p>
    </div>
</section>

<?php if ($tagSeoWarnings !== []): ?>
    <section class="admin-panel">
        <div class="admin-section-head">
            <h2>Taxonomy warnings</h2>
            <p class="meta">These tags look close enough to cannibalize the same search intent.</p>
        </div>
        <div class="admin-record-list">
            <?php foreach ($tags as $tag): ?>
                <?php $warnings = $tagSeoWarnings[(int) ($tag['id'] ?? 0)] ?? []; ?>
                <?php if ($warnings === []): continue; endif; ?>
                <article class="admin-record">
                    <div class="admin-record-summary">
                        <div>
                            <h3>#<?= h((string) $tag['name']) ?></h3>
                            <p class="meta">Consider merging intent, intro copy, or naming.</p>
                        </div>
                    </div>
                    <ul class="admin-inline-list">
                        <?php foreach ($warnings as $warning): ?>
                            <li><?= h($warning) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="admin-management">
    <div class="admin-section-head">
        <h2>Tag SEO</h2>
        <p class="meta"><?= (int) ($adminPagination['total_items'] ?? count($tags)) ?> tags total</p>
    </div>
    <div class="admin-record-list">
        <?php foreach ($tags as $tag): ?>
            <?php $warnings = $tagSeoWarnings[(int) ($tag['id'] ?? 0)] ?? []; ?>
            <article class="admin-record">
                <div class="admin-record-summary">
                    <div>
                        <h3><a href="/tag/<?= h((string) $tag['slug']) ?>">#<?= h((string) $tag['name']) ?></a></h3>
                        <p class="meta"><?= (int) ($tag['question_count'] ?? 0) ?> questions</p>
                    </div>
                    <div class="admin-badges">
                        <span><?= trim((string) ($tag['description'] ?? '')) !== '' ? 'Description' : 'No Description' ?></span>
                        <span><?= trim((string) ($tag['seo_title'] ?? '')) !== '' ? 'SEO Ready' : 'Needs SEO' ?></span>
                    </div>
                </div>
                <form method="post" action="/admin/tags/<?= (int) $tag['id'] ?>/update" class="admin-record-form">
                    <?= $csrf->field('/admin/tags/' . (int) $tag['id'] . '/update') ?>
                    <input type="hidden" name="redirect" value="<?= h($returnTo) ?>">
                    <div class="form-row">
                        <label>Name <input name="name" value="<?= h((string) $tag['name']) ?>" maxlength="80" autocomplete="off" required></label>
                        <label>Description <input name="description" value="<?= h((string) ($tag['description'] ?? '')) ?>" maxlength="240" autocomplete="off" placeholder="Short tag description..."></label>
                    </div>
                    <div class="form-row">
                        <label>SEO Title <input name="seo_title" value="<?= h((string) ($tag['seo_title'] ?? '')) ?>" maxlength="120" autocomplete="off" placeholder="SEO title..."></label>
                        <label>SEO Description <input name="seo_description" value="<?= h((string) ($tag['seo_description'] ?? '')) ?>" maxlength="180" autocomplete="off" placeholder="SEO description..."></label>
                    </div>
                    <label>Intro Content <textarea name="intro" rows="4" maxlength="4000" placeholder="Intro content..."><?= h((string) ($tag['intro'] ?? '')) ?></textarea></label>
                    <?php if ($warnings !== []): ?>
                        <ul class="admin-inline-list">
                            <?php foreach ($warnings as $warning): ?>
                                <li><?= h($warning) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <div class="admin-record-actions"><button type="submit">Save Tag</button></div>
                </form>
            </article>
        <?php endforeach; ?>
        <?php if ($tags === []): ?>
            <p class="admin-empty">No tags yet. Tags are created when questions are posted.</p>
        <?php endif; ?>
    </div>
    <?php require BASE_PATH . '/views/admin/_pagination.php'; ?>
</section>
