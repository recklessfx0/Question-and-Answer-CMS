<?php declare(strict_types=1); ?>
<?php
$categories = is_array($categories ?? null) ? $categories : [];
$categoryCount = (int) ($categoryCount ?? count($categories));
$maxTags = max(1, (int) ($maxTags ?? 8));
?>
<section class="ask-shell">
    <header class="ask-head">
        <div>
            <p class="eyebrow">New question</p>
            <h1>Ask a question</h1>
            <p class="meta">A clear title, useful context, and the right topic signals help the community answer faster.</p>
        </div>
        <div class="ask-summary" aria-label="Question setup summary">
            <span><strong><?= (int) $categoryCount ?></strong> categories</span>
            <span><strong><?= (int) $maxTags ?></strong> tags max</span>
        </div>
    </header>

    <div class="ask-layout">
        <form method="post" action="/ask" enctype="multipart/form-data" class="ask-form">
            <?= $csrf->field('/ask') ?>
            <section class="ask-step" aria-labelledby="ask-title-heading">
                <div class="ask-step-label">1</div>
                <div class="ask-step-body">
                    <h2 id="ask-title-heading">Title</h2>
                    <p class="field-help">Write the exact problem as a question. Duplicate suggestions appear as you type.</p>
                    <label class="sr-only" for="ask-title">Title</label>
                    <input id="ask-title" name="title" minlength="12" maxlength="180" required placeholder="What are you trying to solve?" data-duplicate-title data-count-input data-count-target="ask-title-count">
                    <div class="ask-field-row">
                        <span id="ask-title-count" class="field-help">0 / 180</span>
                    </div>
                    <div class="duplicate-suggestions" data-duplicate-results hidden aria-live="polite"></div>
                </div>
            </section>

            <section class="ask-step" aria-labelledby="ask-topic-heading">
                <div class="ask-step-label">2</div>
                <div class="ask-step-body">
                    <h2 id="ask-topic-heading">Topic</h2>
                    <div class="form-row">
                        <label>Category
                            <select name="category_id">
                                <option value="0">No category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>"><?= h(($category['parent_name'] ? $category['parent_name'] . ' / ' : '') . (string) $category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-help"><?= $categoryCount > 0 ? 'Choose the closest public topic.' : 'No categories are available yet.' ?></span>
                        </label>
                        <label>Tags
                            <input name="tags" maxlength="240" placeholder="Add tags separated by commas or #" aria-describedby="tags-help">
                            <span id="tags-help" class="field-help">Add up to <?= (int) $maxTags ?> tags.</span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="ask-step" aria-labelledby="ask-details-heading">
                <div class="ask-step-label">3</div>
                <div class="ask-step-body">
                    <h2 id="ask-details-heading">Details</h2>
                    <label class="sr-only" for="ask-body">Details</label>
                    <textarea id="ask-body" name="body" rows="12" minlength="30" required data-markdown-editor data-editor-context="question" data-editor-autosave="1" data-editor-min-words="12" placeholder="What happened? What did you try? What result did you expect?"></textarea>
                    <span class="field-help">Use headings, lists, links, quotes, and code to make the question easy to answer.</span>
                </div>
            </section>

            <section class="ask-step" aria-labelledby="ask-media-heading">
                <div class="ask-step-label">4</div>
                <div class="ask-step-body">
                    <h2 id="ask-media-heading">Media</h2>
                    <div class="form-row form-row-actions">
                        <label>Upload media
                            <input
                                name="media[]"
                                type="file"
                                accept="image/png,image/jpeg,image/webp,image/avif,image/gif,video/mp4,video/webm"
                                multiple
                                data-upload-input
                                data-upload-max-files="6"
                                data-upload-max-image-bytes="12000000"
                                data-upload-max-video-bytes="40000000"
                                data-upload-max-total-bytes="60000000"
                                data-upload-empty-message="Images or short videos can make the problem easier to understand."
                            >
                            <span class="field-help">Up to 6 files. Images up to 12 MB each, videos up to 40 MB each.</span>
                            <div class="upload-feedback" data-upload-feedback>
                                <p class="field-help upload-feedback-summary" data-upload-summary>Images or short videos can make the problem easier to understand.</p>
                                <ul class="upload-feedback-list" data-upload-list hidden></ul>
                            </div>
                        </label>
                        <label>Video links
                            <input name="video_urls" maxlength="1000" placeholder="YouTube or Vimeo URL">
                            <span class="field-help">Paste one or more public video URLs.</span>
                        </label>
                    </div>
                    <div class="ask-submit-row">
                        <button type="submit">Publish question</button>
                    </div>
                </div>
            </section>
        </form>

        <aside class="ask-guidance" aria-label="Question checklist">
            <h2>Before publishing</h2>
            <ul>
                <li>Use one specific problem.</li>
                <li>Add what you already tried.</li>
                <li>Include errors, screenshots, or media when useful.</li>
                <li>Choose the closest category and only relevant tags.</li>
            </ul>
            <a href="/community-guidelines">Community guidelines</a>
        </aside>
    </div>
</section>
