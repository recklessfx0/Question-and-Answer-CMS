<?php declare(strict_types=1); ?>
<section class="narrow">
    <h1>Profile settings</h1>
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
</section>
