<?php declare(strict_types=1); ?>
<section class="narrow">
    <h1>Create account</h1>
    <form method="post" action="/register">
        <?= $csrf->field('/register') ?>
        <label>Username <input name="username" minlength="3" maxlength="40" autocomplete="username" required></label>
        <label>Email <input name="email" type="email" autocomplete="email" required></label>
        <label>Password <input name="password" type="password" minlength="12" maxlength="128" autocomplete="new-password" required></label>
        <?= recaptcha_widget($securitySettings ?? []) ?>
        <button type="submit">Create account</button>
    </form>
    <?php if (isset($googleAuthUrl) && is_string($googleAuthUrl)): ?>
        <p><a class="button button-secondary" href="<?= h($googleAuthUrl) ?>">Continue with Google</a></p>
    <?php endif; ?>
</section>
