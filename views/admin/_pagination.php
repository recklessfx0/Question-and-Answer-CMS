<?php declare(strict_types=1); ?>
<?php if (!empty($adminPagination) && is_array($adminPagination) && (int) ($adminPagination['total_pages'] ?? 1) > 1): ?>
    <?php
    $adminPageHref = static function (int $target) use ($adminPagination): string {
        $base = rtrim((string) ($adminPagination['base'] ?? ''), '?&');
        if (!empty($adminPagination['query_mode'])) {
            return $base . ($target > 1 ? (str_contains($base, '?') ? '&' : '?') . 'page=' . $target : '');
        }

        return $base . ($target > 1 ? '?page=' . $target : '');
    };
    ?>
    <nav class="pagination admin-pagination" aria-label="Admin pages">
        <span><?= (int) ($adminPagination['total_items'] ?? 0) ?> items</span>
        <?php if (!empty($adminPagination['previous'])): ?><a href="<?= h((string) $adminPagination['previous']) ?>" rel="prev">Previous</a><?php endif; ?>
        <?php $window = is_array($adminPagination['window'] ?? null) ? $adminPagination['window'] : []; ?>
        <?php if ($window !== [] && (int) $window[0] > 1): ?>
            <a href="<?= h($adminPageHref(1)) ?>">1</a>
            <?php if ((int) $window[0] > 2): ?><span>…</span><?php endif; ?>
        <?php endif; ?>
        <?php foreach ($window as $pageNumber): ?>
            <?php $target = (int) $pageNumber; ?>
            <?php $href = $adminPageHref($target); ?>
            <?php if ($target === (int) ($adminPagination['current'] ?? 1)): ?>
                <span aria-current="page"><?= $target ?></span>
            <?php else: ?>
                <a href="<?= h($href) ?>"><?= $target ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($window !== [] && (int) end($window) < (int) ($adminPagination['total_pages'] ?? 1)): ?>
            <?php if ((int) end($window) < (int) ($adminPagination['total_pages'] ?? 1) - 1): ?><span>…</span><?php endif; ?>
            <?php $last = (int) $adminPagination['total_pages']; ?>
            <a href="<?= h($adminPageHref($last)) ?>"><?= $last ?></a>
        <?php endif; ?>
        <?php if (!empty($adminPagination['next'])): ?><a href="<?= h((string) $adminPagination['next']) ?>" rel="next">Next</a><?php endif; ?>
    </nav>
<?php endif; ?>
