<?php declare(strict_types=1); ?>
<section class="narrow">
    <h1>Two-factor verification</h1>
    <p class="meta">Enter the 6-digit code from your authenticator app.</p>
    <form method="post" action="/two-factor">
        <?= $csrf->field('/two-factor') ?>
        <label>Authentication code <input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>
        <button type="submit">Verify</button>
    </form>
</section>
