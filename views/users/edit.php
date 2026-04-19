<?php declare(strict_types=1); ?>
<section class="narrow">
    <h1>Profile settings</h1>
    <?php if (empty($profile['email_verified_at'])): ?>
        <section class="admin-panel">
            <h2>Email confirmation</h2>
            <p class="meta">Confirm <?= h((string) $profile['email']) ?> so account email features keep working.</p>
            <form method="post" action="/email/verification/resend">
                <?= $csrf->field('/email/verification/resend') ?>
                <button type="submit">Send confirmation email</button>
            </form>
        </section>
    <?php endif; ?>
    <form method="post" action="/settings/profile" enctype="multipart/form-data">
        <?= $csrf->field('/settings/profile') ?>
        <?php if (!empty($profile['avatar_path'])): ?>
            <?= uploaded_image((string) $profile['avatar_path'], 'avatar avatar-large', (string) $profile['username'] . ' avatar') ?>
        <?php endif; ?>
        <label>Username <input name="username" value="<?= h((string) $profile['username']) ?>" minlength="3" maxlength="40" required></label>
        <label>Bio <textarea name="bio" rows="6" maxlength="2000"><?= h((string) ($profile['bio'] ?? '')) ?></textarea></label>
        <label>Website <input name="website_url" type="url" value="<?= h((string) ($profile['website_url'] ?? '')) ?>" maxlength="255" placeholder="https://example.com"></label>
        <label>Expertise topics <input name="expertise" value="<?= h((string) ($profile['expertise'] ?? '')) ?>" maxlength="240" placeholder="PHP security, MySQL, Linux"></label>
        <label>Credentials <input name="credentials" value="<?= h((string) ($profile['credentials'] ?? '')) ?>" maxlength="240" placeholder="Moderator, security engineer, database admin"></label>
        <label>Avatar <input name="avatar" type="file" accept="image/png,image/jpeg,image/webp,image/avif,image/gif"></label>
        <button type="submit">Save profile</button>
    </form>
    <?php if (active_role($profile) === 'administrator'): ?>
        <section class="admin-panel">
            <h2>Administrator two-factor authentication</h2>
            <?php if (!empty($profile['two_factor_enabled_at'])): ?>
                <p class="meta">Two-factor authentication is enabled for this administrator account.</p>
                <form method="post" action="/settings/two-factor/disable">
                    <?= $csrf->field('/settings/two-factor/disable') ?>
                    <label>Current code <input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>
                    <button type="submit">Disable two-factor</button>
                </form>
            <?php else: ?>
                <?php if (empty($profile['two_factor_secret'])): ?>
                    <p class="meta">Generate a setup key, add it to an authenticator app, then confirm a code.</p>
                    <form method="post" action="/settings/two-factor/start">
                        <?= $csrf->field('/settings/two-factor/start') ?>
                        <button type="submit">Generate setup key</button>
                    </form>
                <?php else: ?>
                    <p class="meta">Setup key: <code><?= h((string) $profile['two_factor_secret']) ?></code></p>
                    <p class="meta">Authenticator URI: <code><?= h((string) ($twoFactorProvisioningUri ?? '')) ?></code></p>
                    <form method="post" action="/settings/two-factor/enable">
                        <?= $csrf->field('/settings/two-factor/enable') ?>
                        <label>Authentication code <input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>
                        <button type="submit">Enable two-factor</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
