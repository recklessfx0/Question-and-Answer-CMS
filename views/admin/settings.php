<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<section class="page-head">
    <h1>Site and security settings</h1>
    <p class="meta">Google OAuth redirect URL: <?= h(url('/auth/google/callback')) ?></p>
</section>
<section class="admin-panel">
    <form method="post" action="/admin/settings" class="admin-form" enctype="multipart/form-data">
        <?= $csrf->field('/admin/settings') ?>
        <h2>SEO</h2>
        <label>Site title <input name="site_title" value="<?= h($settings['site_title'] ?? '') ?>" maxlength="80"></label>
        <label>Default meta title <input name="default_meta_title" value="<?= h($settings['default_meta_title'] ?? '') ?>" maxlength="120" placeholder="<?= h((string) ($settings['site_title'] ?? 'Question And Answer Platform')) ?>"></label>
        <label>Website logo <input name="site_logo" type="file" accept="image/avif,image/webp,image/svg+xml,image/jpeg,image/png,image/gif,image/*"></label>
        <?php if (!empty($settings['site_logo_path'])): ?>
            <div class="logo-preview">
                <span>Current logo</span>
                <?= uploaded_image((string) $settings['site_logo_path'], 'site-logo-preview', (string) (($settings['site_title'] ?? '') ?: 'Website logo')) ?>
            </div>
        <?php endif; ?>
        <label>Website URL <input name="site_url" type="url" value="<?= h(($settings['site_url'] ?? '') !== '' ? (string) $settings['site_url'] : site_url()) ?>" maxlength="255" placeholder="https://example.com"></label>
        <p class="meta">Used for canonical URLs, sitemap URLs, robots.txt, llms.txt, structured data, and email links.</p>
        <label>Site description <textarea name="site_description" rows="3" maxlength="200"><?= h($settings['site_description'] ?? '') ?></textarea></label>
        <label>Default meta description <textarea name="default_meta_description" rows="3" maxlength="200" placeholder="Used when a page does not provide its own meta description."><?= h($settings['default_meta_description'] ?? '') ?></textarea></label>
        <div class="form-row">
            <label>Landing page question heading <input name="landing_questions_heading" value="<?= h($settings['landing_questions_heading'] ?? '') ?>" maxlength="120" placeholder="Latest Questions"></label>
            <label>Landing page questions per page <input name="landing_questions_per_page" type="number" min="1" max="50" value="<?= h((string) (($settings['landing_questions_per_page'] ?? '') !== '' ? $settings['landing_questions_per_page'] : '15')) ?>"></label>
        </div>
        <p class="meta">Used for the landing question list heading and pagination size.</p>
        <h2>Social sharing</h2>
        <label>Default Open Graph title <input name="default_og_title" value="<?= h($settings['default_og_title'] ?? '') ?>" maxlength="120" placeholder="Shared page title"></label>
        <label>Default Open Graph description <textarea name="default_og_description" rows="3" maxlength="200" placeholder="Shared page description"><?= h($settings['default_og_description'] ?? '') ?></textarea></label>
        <label>Default Open Graph image <input name="default_og_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif"></label>
        <?php if (!empty($settings['default_og_image_path'])): ?>
            <div class="logo-preview">
                <span>Current sharing image</span>
                <?= uploaded_image((string) $settings['default_og_image_path'], 'site-logo-preview', 'Default social sharing image') ?>
            </div>
        <?php endif; ?>
        <label>Favicon <input name="site_favicon" type="file" accept="image/avif,image/webp,image/svg+xml,image/jpeg,image/png,image/gif,image/*"></label>
        <?php if (!empty($settings['site_favicon_path'])): ?>
            <div class="logo-preview">
                <span>Current favicon</span>
                <?= uploaded_image((string) $settings['site_favicon_path'], 'site-logo-preview', 'Website favicon') ?>
            </div>
        <?php endif; ?>
        <div class="form-row">
            <label>X/Twitter site handle <input name="twitter_site" value="<?= h($settings['twitter_site'] ?? '') ?>" maxlength="31" placeholder="@site"></label>
            <label>X/Twitter creator handle <input name="twitter_creator" value="<?= h($settings['twitter_creator'] ?? '') ?>" maxlength="31" placeholder="@creator"></label>
        </div>
        <h2>Google reCAPTCHA</h2>
        <label class="checkbox-label"><input name="recaptcha_enabled" type="checkbox" value="1" <?= setting_enabled($settings, 'recaptcha_enabled') ? 'checked' : '' ?>> Enable reCAPTCHA on account forms</label>
        <label>Site key <input name="recaptcha_site_key" value="<?= h($settings['recaptcha_site_key'] ?? '') ?>" maxlength="255" autocomplete="off"></label>
        <label>Secret key <input name="recaptcha_secret_key" type="password" maxlength="255" autocomplete="new-password" placeholder="<?= empty($settings['recaptcha_secret_key'] ?? '') ? 'Paste secret key' : 'Leave blank to keep current secret' ?>"></label>
        <h2>Google sign-in</h2>
        <label class="checkbox-label"><input name="google_auth_enabled" type="checkbox" value="1" <?= setting_enabled($settings, 'google_auth_enabled') ? 'checked' : '' ?>> Enable Google sign-in</label>
        <label>Client ID <input name="google_client_id" value="<?= h($settings['google_client_id'] ?? '') ?>" maxlength="255" autocomplete="off"></label>
        <label>Client secret <input name="google_client_secret" type="password" maxlength="255" autocomplete="new-password" placeholder="<?= empty($settings['google_client_secret'] ?? '') ? 'Paste client secret' : 'Leave blank to keep current secret' ?>"></label>
        <h2>Question moderation</h2>
        <label class="checkbox-label"><input name="question_approval_enabled" type="checkbox" value="1" <?= setting_enabled($settings, 'question_approval_enabled') ? 'checked' : '' ?>> Require moderator approval for new questions</label>
        <p class="meta">New questions stay private until an administrator or moderator approves them under <a href="/moderate">/moderate</a>.</p>
        <h2>SMTP email</h2>
        <label class="checkbox-label"><input name="smtp_enabled" type="checkbox" value="1" <?= setting_enabled($settings, 'smtp_enabled') ? 'checked' : '' ?>> Send email through SMTP</label>
        <label>SMTP host <input name="smtp_host" value="<?= h($settings['smtp_host'] ?? '') ?>" maxlength="255" autocomplete="off" placeholder="smtp.example.com"></label>
        <label>SMTP port <input name="smtp_port" type="number" min="1" max="65535" value="<?= h((string) ($settings['smtp_port'] ?? '587')) ?>"></label>
        <label>Encryption
            <select name="smtp_encryption">
                <?php foreach (['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => 'None'] as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= ($settings['smtp_encryption'] ?? 'tls') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>SMTP username <input name="smtp_username" value="<?= h($settings['smtp_username'] ?? '') ?>" maxlength="255" autocomplete="off"></label>
        <label>SMTP password <input name="smtp_password" type="password" maxlength="255" autocomplete="new-password" placeholder="<?= empty($settings['smtp_password'] ?? '') ? 'Paste SMTP password' : 'Leave blank to keep current password' ?>"></label>
        <label>From email <input name="smtp_from_email" type="email" value="<?= h($settings['smtp_from_email'] ?? '') ?>" maxlength="255" placeholder="no-reply@example.com"></label>
        <label>From name <input name="smtp_from_name" value="<?= h($settings['smtp_from_name'] ?? '') ?>" maxlength="120" placeholder="<?= h((string) ($settings['site_title'] ?? 'Question And Answer Platform')) ?>"></label>
        <button type="submit">Save settings</button>
    </form>
</section>
