<?php declare(strict_types=1); ?>
<?php $welcome = is_array($pageWidgets['welcome'] ?? null) ? $pageWidgets['welcome'] : null; ?>
<?php if ($welcome !== null): ?>
    <section class="guest-welcome" aria-label="<?= h(t('widgets.welcome.label', 'Guest welcome')) ?>">
        <div class="guest-welcome-copy">
            <strong><?= h((string) ($welcome['title'] ?? '')) ?></strong>
            <p><?= h((string) ($welcome['message'] ?? '')) ?></p>
        </div>
        <div class="guest-welcome-action">
            <a class="button guest-welcome-button" href="/register"><?= h((string) ($welcome['button_label'] ?? t('widgets.welcome.register', 'Register'))) ?></a>
        </div>
    </section>
<?php endif; ?>
