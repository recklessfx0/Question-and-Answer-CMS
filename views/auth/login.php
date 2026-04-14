<?php declare(strict_types=1); ?>
<section class="narrow">
    <h1>Sign in</h1>
    <form method="post" action="/login">
        <?= $csrf->field('/login') ?>
        <label>Email <input name="email" type="email" autocomplete="email" required></label>
        <label>Password <input name="password" type="password" autocomplete="current-password" required></label>
        <?= recaptcha_widget($securitySettings ?? []) ?>
        <button type="submit">Sign in</button>
    </form>
    <?php if (isset($googleAuthUrl) && is_string($googleAuthUrl)): ?>
        <p><a class="button button-secondary" href="<?= h($googleAuthUrl) ?>">Continue with Google</a></p>
    <?php endif; ?>
    <p><a href="/forgot-password">Forgot your password?</a></p>
</section>
