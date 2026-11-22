<?php declare(strict_types=1); ?>
<?php require_once BASE_PATH . '/views/partials/icon.php'; ?>
<section class="page-head">
    <h1><?= h($title ?? 'Tags') ?></h1>
    <p>Find questions by topic.</p>
</section>
<section class="directory-grid">
    <?php foreach ($tags as $tag): ?>
        <a class="directory-card topic-card" href="<?= h(localized_path('/tag/' . (string) $tag['slug'])) ?>">
            <span class="topic-mark"><?= ui_icon('topics') ?></span>
            <strong>#<?= h((string) $tag['name']) ?></strong>
            <span><?= (int) $tag['question_count'] ?> questions</span>
        </a>
    <?php endforeach; ?>
</section>

<?php if (!empty($pagination) && is_array($pagination) && (int) ($pagination['total_pages'] ?? 1) > 1): ?>
    <nav class="pagination" aria-label="Tag pages">
        <span><?= (int) ($pagination['total_items'] ?? 0) ?> tags</span>
        <?php if (!empty($pagination['previous'])): ?><a href="<?= h((string) $pagination['previous']) ?>" rel="prev">Previous</a><?php endif; ?>
        <?php $window = is_array($pagination['window'] ?? null) ? $pagination['window'] : []; ?>
        <?php if ($window !== [] && (int) $window[0] > 1): ?>
            <a href="<?= h((string) ($pagination['query_mode'] ?? false ? $pagination['base'] : rtrim((string) $pagination['base'], '/'))) ?>">1</a>
            <?php if ((int) $window[0] > 2): ?><span>...</span><?php endif; ?>
        <?php endif; ?>
        <?php foreach ($window as $pageNumber): ?>
            <?php $target = (int) $pageNumber; ?>
            <?php
            $href = !empty($pagination['query_mode'])
                ? (string) $pagination['base'] . ($target > 1 ? '&page=' . $target : '')
                : ($target > 1 ? rtrim((string) $pagination['base'], '/') . '/page/' . $target : rtrim((string) $pagination['base'], '/'));
            ?>
            <?php if ($target === (int) ($pagination['current'] ?? 1)): ?>
                <span aria-current="page"><?= $target ?></span>
            <?php else: ?>
                <a href="<?= h($href) ?>"><?= $target ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($window !== [] && (int) end($window) < (int) ($pagination['total_pages'] ?? 1)): ?>
            <?php if ((int) end($window) < (int) ($pagination['total_pages'] ?? 1) - 1): ?><span>...</span><?php endif; ?>
            <?php $last = (int) $pagination['total_pages']; ?>
            <a href="<?= h(!empty($pagination['query_mode']) ? (string) $pagination['base'] . '&page=' . $last : rtrim((string) $pagination['base'], '/') . '/page/' . $last) ?>"><?= $last ?></a>
        <?php endif; ?>
        <?php if (!empty($pagination['next'])): ?><a href="<?= h((string) $pagination['next']) ?>" rel="next">Next</a><?php endif; ?>
    </nav>
<?php endif; ?>
