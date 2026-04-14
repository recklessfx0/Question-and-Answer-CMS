<?php declare(strict_types=1); ?>
<section class="ask-shell">
    <h1>Ask a question</h1>
    <p class="meta">Use a specific title, add the context someone needs, and tag it so the right people can find it.</p>
    <form method="post" action="/ask" enctype="multipart/form-data" class="ask-form">
        <?= $csrf->field('/ask') ?>
        <div class="form-row form-row-full">
            <label>Title
                <input name="title" minlength="12" maxlength="180" required placeholder="How do I configure secure PHP sessions?" data-duplicate-title>
            </label>
            <div class="duplicate-suggestions" data-duplicate-results hidden aria-live="polite"></div>
        </div>
        <div class="form-row">
            <label>Category
                <select name="category_id">
                    <option value="0">No category</option>
                    <?php foreach ($categories ?? [] as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"><?= h(($category['parent_name'] ? $category['parent_name'] . ' / ' : '') . (string) $category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Tags
                <input name="tags" maxlength="300" placeholder="php, security, mysql">
            </label>
        </div>
        <div class="form-row form-row-full">
            <label>Details
                <textarea name="body" rows="12" minlength="30" required data-markdown-editor placeholder="What did you try? What result did you expect? What happened instead?"></textarea>
            </label>
        </div>
        <div class="form-row form-row-actions">
            <label>Media
                <input name="media[]" type="file" accept="image/png,image/jpeg,image/webp,image/avif,image/gif,video/mp4,video/webm" multiple>
            </label>
            <label>Video links
                <input name="video_urls" maxlength="1000" placeholder="YouTube or Vimeo URL">
            </label>
            <button type="submit">Publish question</button>
        </div>
    </form>
</section>
