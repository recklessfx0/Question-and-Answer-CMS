<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<?php
$config = is_array($widgetConfig ?? null) ? $widgetConfig : [];
$widgets = is_array($config['widgets'] ?? null) ? $config['widgets'] : [];
$welcome = is_array($config['welcome'] ?? null) ? $config['welcome'] : [];
$types = is_array($widgetTypes ?? null) ? $widgetTypes : [];
$zones = is_array($widgetZones ?? null) ? $widgetZones : [];
$pages = is_array($widgetPages ?? null) ? $widgetPages : [];
?>
<section class="page-head admin-page-head">
    <div>
        <h1><?= h(t('admin.widgets.title', 'Dynamic widgets')) ?></h1>
        <p class="meta"><?= h(t('admin.widgets.description', 'Choose which dynamic widgets appear on landing and question pages.')) ?></p>
    </div>
</section>

<form method="post" action="/admin/widgets" class="admin-form admin-form-enhanced widget-settings-form">
    <?= $csrf->field('/admin/widgets') ?>

    <section class="admin-create-panel widget-settings-panel">
        <div class="admin-section-head">
            <div>
                <h2><?= h(t('admin.widgets.global_title', 'Global display')) ?></h2>
                <p class="meta"><?= h(t('admin.widgets.global_help', 'Turn the widget columns on or off without losing each widget setup.')) ?></p>
            </div>
        </div>
        <label class="checkbox-label">
            <input type="checkbox" name="widgets_enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>>
            <?= h(t('admin.widgets.enable_all', 'Enable dynamic widget columns')) ?>
        </label>
    </section>

    <section class="admin-create-panel widget-settings-panel">
        <div class="admin-section-head">
            <div>
                <h2><?= h(t('admin.widgets.welcome_title', 'Guest welcome message')) ?></h2>
                <p class="meta"><?= h(t('admin.widgets.welcome_help', 'Show a registration prompt to guests on selected public pages.')) ?></p>
            </div>
        </div>
        <label class="checkbox-label">
            <input type="checkbox" name="guest_welcome[enabled]" value="1" <?= !empty($welcome['enabled']) ? 'checked' : '' ?>>
            <?= h(t('admin.widgets.welcome_enable', 'Show welcome message to guests')) ?>
        </label>
        <div class="widget-page-options" aria-label="<?= h(t('admin.widgets.welcome_pages', 'Welcome message pages')) ?>">
            <?php foreach ($pages as $pageKey => $pageLabel): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="guest_welcome[pages][]" value="<?= h((string) $pageKey) ?>" <?= in_array($pageKey, (array) ($welcome['pages'] ?? []), true) ? 'checked' : '' ?>>
                    <?= h((string) $pageLabel) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="form-row">
            <label><?= h(t('admin.widgets.welcome_heading', 'Heading')) ?>
                <input name="guest_welcome[title]" value="<?= h((string) ($welcome['title'] ?? '')) ?>" maxlength="90">
            </label>
            <label><?= h(t('admin.widgets.welcome_button', 'Button label')) ?>
                <input name="guest_welcome[button_label]" value="<?= h((string) ($welcome['button_label'] ?? '')) ?>" maxlength="40">
            </label>
        </div>
        <label><?= h(t('admin.widgets.welcome_message', 'Message')) ?>
            <textarea name="guest_welcome[message]" rows="3" maxlength="220"><?= h((string) ($welcome['message'] ?? '')) ?></textarea>
        </label>
    </section>

    <section class="admin-management">
        <div class="admin-section-head">
            <div>
                <h2><?= h(t('admin.widgets.available_title', 'Available widgets')) ?></h2>
                <p class="meta"><?= h(t('admin.widgets.available_help', 'Each widget can be enabled independently, placed in a side column, and assigned to landing or question pages.')) ?></p>
            </div>
        </div>
        <div class="admin-record-list widget-config-list">
            <?php foreach ($types as $type => $meta): ?>
                <?php $widget = is_array($widgets[$type] ?? null) ? $widgets[$type] : []; ?>
                <article class="admin-record widget-config-card">
                    <div class="admin-record-summary">
                        <div>
                            <h3><?= h((string) ($meta['label'] ?? $type)) ?></h3>
                            <p class="meta"><?= h((string) ($meta['description'] ?? '')) ?></p>
                        </div>
                        <label class="checkbox-label widget-enable-toggle">
                            <input type="checkbox" name="widgets[<?= h((string) $type) ?>][enabled]" value="1" <?= !empty($widget['enabled']) ? 'checked' : '' ?>>
                            <?= h(t('admin.widgets.enable_widget', 'Enabled')) ?>
                        </label>
                    </div>
                    <div class="widget-config-grid">
                        <label><?= h(t('admin.widgets.custom_title', 'Widget title')) ?>
                            <input name="widgets[<?= h((string) $type) ?>][title]" value="<?= h((string) ($widget['title'] ?? ($meta['label'] ?? ''))) ?>" maxlength="80">
                        </label>
                        <label><?= h(t('admin.widgets.zone', 'Column')) ?>
                            <select name="widgets[<?= h((string) $type) ?>][zone]">
                                <?php foreach ($zones as $zoneKey => $zoneLabel): ?>
                                    <option value="<?= h((string) $zoneKey) ?>" <?= (string) ($widget['zone'] ?? 'right') === (string) $zoneKey ? 'selected' : '' ?>><?= h((string) $zoneLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label><?= h(t('admin.widgets.limit', 'Items')) ?>
                            <input type="number" name="widgets[<?= h((string) $type) ?>][limit]" value="<?= (int) ($widget['limit'] ?? 5) ?>" min="1" max="12">
                        </label>
                    </div>
                    <div class="widget-page-options" aria-label="<?= h((string) ($meta['label'] ?? $type) . ' pages') ?>">
                        <?php foreach ($pages as $pageKey => $pageLabel): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="widgets[<?= h((string) $type) ?>][pages][]" value="<?= h((string) $pageKey) ?>" <?= in_array($pageKey, (array) ($widget['pages'] ?? []), true) ? 'checked' : '' ?>>
                                <?= h((string) $pageLabel) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="admin-form-actions">
        <button type="submit"><?= h(t('admin.widgets.save', 'Save widget settings')) ?></button>
    </div>
</form>
