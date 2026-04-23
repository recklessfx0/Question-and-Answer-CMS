<?php declare(strict_types=1); ?>
<section class="auth-page auth-page-forgot-password">
    <div class="auth-panel">
        <header class="auth-head">
            <p class="eyebrow"><?= h(t('auth.forgot.eyebrow', 'Account recovery')) ?></p>
            <h1><?= h(t('auth.forgot.title', 'Reset Password')) ?></h1>
            <p><?= h(t('auth.forgot.description', 'Enter your account email and a secure reset link will be sent if the account exists.')) ?></p>
        </header>
<form method="post" action="<?= h(localized_path('/forgot-password')) ?>" class="auth-form">
            <?= $csrf->field('/forgot-password') ?>
            <label><?= h(t('auth.email', 'Email')) ?><input name="email" type="email" autocomplete="email" spellcheck="false" required></label>
            <?= recaptcha_widget($securitySettings ?? []) ?>
            <button type="submit"><?= h(t('auth.forgot.submit', 'Send Reset Link')) ?></button>
        </form>
        <div class="auth-links">
<a href="<?= h(localized_path('/login')) ?>"><?= h(t('auth.forgot.back_to_login', 'Back to sign in')) ?></a>
<a href="<?= h(localized_path('/register')) ?>"><?= h(t('auth.login.create_account', 'Create an account')) ?></a>
        </div>
    </div>
</section>
