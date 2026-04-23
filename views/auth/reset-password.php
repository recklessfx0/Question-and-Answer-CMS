<?php declare(strict_types=1); ?>
<section class="narrow">
    <h1>Choose a new password</h1>
<form method="post" action="<?= h(localized_path('/reset-password/' . (string) $token)) ?>">
        <?= $csrf->field('/reset-password/' . (string) $token) ?>
        <label>New password <input name="password" type="password" minlength="12" maxlength="128" autocomplete="new-password" required></label>
        <button type="submit">Update password</button>
    </form>
</section>
