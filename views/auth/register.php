<?php declare(strict_types=1); ?>
<section class="auth-page auth-page-register">
    <div class="auth-panel">
        <header class="auth-head">
            <p class="eyebrow"><?= h(t('auth.register.eyebrow', 'Join the community')) ?></p>
            <h1><?= h(t('auth.register.title', 'Create Account')) ?></h1>
            <p><?= h(t('auth.register.description', 'Ask questions, answer others, react to useful posts, and build reputation.')) ?></p>
        </header>
<form method="post" action="<?= h(localized_path('/register')) ?>" class="auth-form">
            <?= $csrf->field('/register') ?>
            <label><?= h(t('auth.username', 'Username')) ?><input name="username" minlength="3" maxlength="40" autocomplete="username" spellcheck="false" required></label>
            <label><?= h(t('auth.email', 'Email')) ?><input name="email" type="email" autocomplete="email" spellcheck="false" required></label>
            <label><?= h(t('auth.password', 'Password')) ?><input name="password" type="password" minlength="12" maxlength="128" autocomplete="new-password" required><span class="field-help"><?= h(t('auth.register.password_help', 'Use at least 12 characters.')) ?></span></label>
            <?= recaptcha_widget($securitySettings ?? []) ?>
            <button type="submit"><?= h(t('auth.register.submit', 'Create Account')) ?></button>
        </form>
        <?php if (isset($googleAuthUrl) && is_string($googleAuthUrl)): ?>
            <div class="auth-divider"><span><?= h(t('auth.or', 'Or')) ?></span></div>
            <a class="button button-secondary auth-provider-button" href="<?= h($googleAuthUrl) ?>"><?= h(t('auth.google.continue', 'Continue with Google')) ?></a>
        <?php endif; ?>
        <div class="auth-links">
<a href="<?= h(localized_path('/login')) ?>"><?= h(t('auth.register.have_account', 'Already have an account?')) ?></a>
        </div>
    </div>
</section>
