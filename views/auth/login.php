<?php declare(strict_types=1); ?>
<section class="auth-page auth-page-login">
    <div class="auth-panel">
        <header class="auth-head">
            <p class="eyebrow"><?= h(t('auth.login.eyebrow', 'Welcome back')) ?></p>
            <h1><?= h(t('auth.login.title', 'Sign In')) ?></h1>
            <p><?= h(t('auth.login.description', 'Continue your questions, answers, notifications, and saved settings.')) ?></p>
        </header>
<form method="post" action="<?= h(localized_path('/login')) ?>" class="auth-form">
            <?= $csrf->field('/login') ?>
            <label><?= h(t('auth.email', 'Email')) ?><input name="email" type="email" autocomplete="email" spellcheck="false" required></label>
            <label><?= h(t('auth.password', 'Password')) ?><input name="password" type="password" autocomplete="current-password" required></label>
            <?= recaptcha_widget($securitySettings ?? []) ?>
            <button type="submit"><?= h(t('auth.login.submit', 'Sign In')) ?></button>
        </form>
        <?php if (isset($googleAuthUrl) && is_string($googleAuthUrl)): ?>
            <div class="auth-divider"><span><?= h(t('auth.or', 'Or')) ?></span></div>
            <a class="button button-secondary auth-provider-button" href="<?= h($googleAuthUrl) ?>"><?= h(t('auth.google.continue', 'Continue with Google')) ?></a>
        <?php endif; ?>
        <div class="auth-links">
<a href="<?= h(localized_path('/forgot-password')) ?>"><?= h(t('auth.login.forgot_password', 'Forgot your password?')) ?></a>
<a href="<?= h(localized_path('/register')) ?>"><?= h(t('auth.login.create_account', 'Create an account')) ?></a>
        </div>
    </div>
</section>
