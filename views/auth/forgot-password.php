<?php declare(strict_types=1); ?>
<section class="narrow">
    <h1>Reset password</h1>
    <form method="post" action="/forgot-password">
        <?= $csrf->field('/forgot-password') ?>
        <label>Email <input name="email" type="email" autocomplete="email" required></label>
        <?= recaptcha_widget($securitySettings ?? []) ?>
        <button type="submit">Send reset link</button>
    </form>
</section>
