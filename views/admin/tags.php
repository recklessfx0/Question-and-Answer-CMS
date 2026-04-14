<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<section class="page-head">
    <h1>Tags</h1>
    <p class="meta">Add topical descriptions and SEO snippets for tag hubs. Tags are created from questions.</p>
</section>
<section class="table-wrap">
    <h2>Tag SEO</h2>
    <table>
        <thead><tr><th>Tag</th><th>Questions</th><th>Update</th></tr></thead>
        <tbody>
        <?php foreach ($tags as $tag): ?>
            <tr>
                <td><a href="/tag/<?= h((string) $tag['slug']) ?>">#<?= h((string) $tag['name']) ?></a></td>
                <td><?= (int) ($tag['question_count'] ?? 0) ?></td>
                <td>
                    <form method="post" action="/admin/tags/<?= (int) $tag['id'] ?>/update" class="admin-table-form">
                        <?= $csrf->field('/admin/tags/' . (int) $tag['id'] . '/update') ?>
                        <input name="name" value="<?= h((string) $tag['name']) ?>" maxlength="80" required>
                        <input name="description" value="<?= h((string) ($tag['description'] ?? '')) ?>" maxlength="240" placeholder="Short tag description">
                        <input name="seo_title" value="<?= h((string) ($tag['seo_title'] ?? '')) ?>" maxlength="120" placeholder="SEO title">
                        <input name="seo_description" value="<?= h((string) ($tag['seo_description'] ?? '')) ?>" maxlength="180" placeholder="SEO description">
                        <textarea name="intro" rows="4" maxlength="4000" placeholder="Intro content"><?= h((string) ($tag['intro'] ?? '')) ?></textarea>
                        <button type="submit">Save</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
