<?php declare(strict_types=1); ?>
<section class="narrow">
    <h1><?= h($status ?? 500) ?></h1>
    <p><?= h($message ?? 'Something went wrong') ?></p>
    <a class="button" href="/">Back to questions</a>
</section>
