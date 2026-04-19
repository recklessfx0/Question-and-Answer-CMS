<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<section class="page-head">
    <h1>Trust pages</h1>
    <p class="meta">Manage the public pages visitors use to understand the site, its policies, and its community standards.</p>
</section>
<section class="admin-panel">
    <form method="post" action="/admin/trust-pages" class="admin-form trust-page-form">
        <?= $csrf->field('/admin/trust-pages') ?>
        <section class="trust-editor-section">
            <h2>About</h2>
            <label>About description <textarea name="about_description" rows="2" maxlength="240"><?= h($settings['about_description'] ?? '') ?></textarea></label>
            <label>About body <textarea name="about_body" rows="5" maxlength="4000"><?= h($settings['about_body'] ?? '') ?></textarea></label>
        </section>
        <section class="trust-editor-section">
            <h2>Contact</h2>
            <label>Contact description <textarea name="contact_description" rows="2" maxlength="240"><?= h($settings['contact_description'] ?? '') ?></textarea></label>
            <label>Contact body <textarea name="contact_body" rows="5" maxlength="4000"><?= h($settings['contact_body'] ?? '') ?></textarea></label>
        </section>
        <section class="trust-editor-section">
            <h2>Privacy</h2>
            <label>Privacy description <textarea name="privacy_description" rows="2" maxlength="240"><?= h($settings['privacy_description'] ?? '') ?></textarea></label>
            <label>Privacy body <textarea name="privacy_body" rows="5" maxlength="4000"><?= h($settings['privacy_body'] ?? '') ?></textarea></label>
        </section>
        <section class="trust-editor-section">
            <h2>Terms</h2>
            <label>Terms description <textarea name="terms_description" rows="2" maxlength="240"><?= h($settings['terms_description'] ?? '') ?></textarea></label>
            <label>Terms body <textarea name="terms_body" rows="5" maxlength="4000"><?= h($settings['terms_body'] ?? '') ?></textarea></label>
        </section>
        <section class="trust-editor-section">
            <h2>Community guidelines</h2>
            <label>Community guidelines description <textarea name="guidelines_description" rows="2" maxlength="240"><?= h($settings['guidelines_description'] ?? '') ?></textarea></label>
            <label>Community guidelines body <textarea name="guidelines_body" rows="5" maxlength="4000"><?= h($settings['guidelines_body'] ?? '') ?></textarea></label>
        </section>
        <button type="submit">Save trust pages</button>
    </form>
</section>
