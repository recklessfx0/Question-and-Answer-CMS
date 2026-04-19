<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<section class="page-head">
    <h1><?= h(t('admin.languages.title', 'Languages')) ?></h1>
    <p><?= h(t('admin.languages.description', 'Translate interface text and control which languages are available to visitors.')) ?></p>
</section>

<section class="admin-panel language-admin">
    <form method="post" action="/admin/languages" class="language-create-form">
        <?= $csrf->field('/admin/languages') ?>
        <h2><?= h(t('admin.languages.add', 'Add language')) ?></h2>
        <label><?= h(t('admin.languages.code', 'Language code')) ?> <input name="code" maxlength="20" placeholder="de" required></label>
        <label><?= h(t('admin.languages.name', 'Language name')) ?> <input name="name" maxlength="80" placeholder="Deutsch" required></label>
        <label class="checkbox-label"><input name="is_active" type="checkbox" value="1" checked> <?= h(t('admin.languages.active', 'Active')) ?></label>
        <label class="checkbox-label"><input name="is_default" type="checkbox" value="1"> <?= h(t('admin.languages.default', 'Default')) ?></label>
        <button type="submit"><?= h(t('admin.languages.create', 'Create language')) ?></button>
    </form>

    <div class="language-list" aria-label="<?= h(t('admin.languages.title', 'Languages')) ?>">
        <?php foreach ($languages as $language): ?>
            <form method="post" action="/admin/languages/<?= (int) $language['id'] ?>/update" class="language-row">
                <?= $csrf->field('/admin/languages/' . (int) $language['id'] . '/update') ?>
                <input type="hidden" name="code" value="<?= h((string) $language['code']) ?>">
                <a href="/admin/languages?code=<?= h(rawurlencode((string) $language['code'])) ?>" class="<?= isset($selectedLanguage['id']) && (int) $selectedLanguage['id'] === (int) $language['id'] ? 'active' : '' ?>">
                    <strong><?= h((string) $language['name']) ?></strong>
                    <span><?= h((string) $language['code']) ?></span>
                </a>
                <label><span class="sr-only"><?= h(t('admin.languages.name', 'Language name')) ?></span><input name="name" value="<?= h((string) $language['name']) ?>" maxlength="80" required></label>
                <label class="checkbox-label"><input name="is_active" type="checkbox" value="1" <?= (int) $language['is_active'] === 1 ? 'checked' : '' ?>> <?= h(t('admin.languages.active', 'Active')) ?></label>
                <label class="checkbox-label"><input name="is_default" type="checkbox" value="1" <?= (int) $language['is_default'] === 1 ? 'checked' : '' ?>> <?= h(t('admin.languages.default', 'Default')) ?></label>
                <button type="submit"><?= h(t('common.save', 'Save')) ?></button>
            </form>
        <?php endforeach; ?>
    </div>
</section>

<?php if (is_array($selectedLanguage)): ?>
    <section class="admin-panel translation-editor">
        <div class="admin-section-head">
            <div>
                <h2><?= h(t('admin.languages.translations', 'Translations')) ?>: <?= h((string) $selectedLanguage['name']) ?></h2>
                <p class="meta"><?= h(t('admin.languages.json_help', 'Upload or export JSON translation files so communities can share language packs.')) ?></p>
            </div>
            <a class="button button-secondary" href="/admin/languages/<?= (int) $selectedLanguage['id'] ?>/export"><?= h(t('admin.languages.export_json', 'Export JSON')) ?></a>
        </div>
        <form method="post" action="/admin/languages/<?= (int) $selectedLanguage['id'] ?>/import" enctype="multipart/form-data" class="language-import-form">
            <?= $csrf->field('/admin/languages/' . (int) $selectedLanguage['id'] . '/import') ?>
            <input type="hidden" name="code" value="<?= h((string) $selectedLanguage['code']) ?>">
            <label><?= h(t('admin.languages.import_json', 'Import JSON')) ?> <input name="translations_json" type="file" accept="application/json,.json" required></label>
            <button type="submit"><?= h(t('admin.languages.upload_json', 'Upload JSON')) ?></button>
        </form>
        <form method="post" action="/admin/languages/<?= (int) $selectedLanguage['id'] ?>/translations">
            <?= $csrf->field('/admin/languages/' . (int) $selectedLanguage['id'] . '/translations') ?>
            <input type="hidden" name="code" value="<?= h((string) $selectedLanguage['code']) ?>">
            <div class="translation-grid">
                <?php foreach ($defaults as $key => $english): ?>
                    <label>
                        <span><?= h((string) $key) ?></span>
                        <small><?= h((string) $english) ?></small>
                        <textarea name="translations[<?= h((string) $key) ?>]" rows="2"><?= h((string) ($translations[$key] ?? $english)) ?></textarea>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="submit"><?= h(t('admin.languages.save', 'Save translations')) ?></button>
        </form>
    </section>
<?php endif; ?>
