<?php declare(strict_types=1);

$adminPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/admin'), PHP_URL_PATH) ?: '/admin';
$adminItems = [
    '/admin' => 'Overview',
    '/admin/settings' => 'Settings',
    '/admin/trust-pages' => 'Trust Pages',
    '/admin/languages' => 'Languages',
    '/admin/categories' => 'Categories',
    '/admin/tags' => 'Tags',
    '/admin/levels' => 'Levels',
    '/admin/reactions' => 'Reactions',
    '/admin/users' => 'Users',
    '/admin/reports' => 'Reports',
];
?>
<nav class="admin-nav" aria-label="Admin sections">
    <?php foreach ($adminItems as $href => $label): ?>
        <a href="<?= h($href) ?>" class="<?= $adminPath === $href ? 'active' : '' ?>"><?= h(t('admin.nav.' . strtolower(str_replace(' ', '_', $label)), $label)) ?></a>
    <?php endforeach; ?>
</nav>
