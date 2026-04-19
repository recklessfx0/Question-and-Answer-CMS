<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<section class="page-head">
    <h1>Site and security settings</h1>
    <p class="meta">Google OAuth redirect URL: <?= h(url('/auth/google/callback')) ?></p>
</section>
<section class="admin-panel">
    <form method="post" action="/admin/settings" class="admin-form">
        <?= $csrf->field('/admin/settings') ?>
        <h2>SEO</h2>
        <label>Site title <input name="site_title" value="<?= h($settings['site_title'] ?? '') ?>" maxlength="100"></label>
        <label>Website URL <input name="site_url" type="url" value="<?= h(($settings['site_url'] ?? '') !== '' ? (string) $settings['site_url'] : site_url()) ?>" maxlength="255" placeholder="https://example.com"></label>
        <p class="meta">Used for canonical URLs, sitemap URLs, robots.txt, llms.txt, structured data, and email links.</p>
        <label>Site description <textarea name="site_description" rows="3" maxlength="200"><?= h($settings['site_description'] ?? '') ?></textarea></label>
        <h2>Trust pages</h2>
        <label>About description <textarea name="about_description" rows="2" maxlength="240"><?= h($settings['about_description'] ?? '') ?></textarea></label>
        <label>About body <textarea name="about_body" rows="5" maxlength="4000"><?= h($settings['about_body'] ?? '') ?></textarea></label>
        <label>Contact description <textarea name="contact_description" rows="2" maxlength="240"><?= h($settings['contact_description'] ?? '') ?></textarea></label>
        <label>Contact body <textarea name="contact_body" rows="5" maxlength="4000"><?= h($settings['contact_body'] ?? '') ?></textarea></label>
        <label>Privacy description <textarea name="privacy_description" rows="2" maxlength="240"><?= h($settings['privacy_description'] ?? '') ?></textarea></label>
        <label>Privacy body <textarea name="privacy_body" rows="5" maxlength="4000"><?= h($settings['privacy_body'] ?? '') ?></textarea></label>
        <label>Terms description <textarea name="terms_description" rows="2" maxlength="240"><?= h($settings['terms_description'] ?? '') ?></textarea></label>
        <label>Terms body <textarea name="terms_body" rows="5" maxlength="4000"><?= h($settings['terms_body'] ?? '') ?></textarea></label>
        <label>Community guidelines description <textarea name="guidelines_description" rows="2" maxlength="240"><?= h($settings['guidelines_description'] ?? '') ?></textarea></label>
        <label>Community guidelines body <textarea name="guidelines_body" rows="5" maxlength="4000"><?= h($settings['guidelines_body'] ?? '') ?></textarea></label>
        <h2>Google reCAPTCHA</h2>
        <label class="checkbox-label"><input name="recaptcha_enabled" type="checkbox" value="1" <?= setting_enabled($settings, 'recaptcha_enabled') ? 'checked' : '' ?>> Enable reCAPTCHA on account forms</label>
        <label>Site key <input name="recaptcha_site_key" value="<?= h($settings['recaptcha_site_key'] ?? '') ?>" maxlength="255" autocomplete="off"></label>
        <label>Secret key <input name="recaptcha_secret_key" type="password" maxlength="255" autocomplete="new-password" placeholder="<?= empty($settings['recaptcha_secret_key'] ?? '') ? 'Paste secret key' : 'Leave blank to keep current secret' ?>"></label>
        <h2>Google sign-in</h2>
        <label class="checkbox-label"><input name="google_auth_enabled" type="checkbox" value="1" <?= setting_enabled($settings, 'google_auth_enabled') ? 'checked' : '' ?>> Enable Google sign-in</label>
        <label>Client ID <input name="google_client_id" value="<?= h($settings['google_client_id'] ?? '') ?>" maxlength="255" autocomplete="off"></label>
        <label>Client secret <input name="google_client_secret" type="password" maxlength="255" autocomplete="new-password" placeholder="<?= empty($settings['google_client_secret'] ?? '') ? 'Paste client secret' : 'Leave blank to keep current secret' ?>"></label>
        <button type="submit">Save settings</button>
    </form>
</section>
