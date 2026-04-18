<?php

declare(strict_types=1);

$site = (require BASE_PATH . '/config/app.php')['name'];
$pageTitle = isset($title) ? (string) $title . ' | ' . $site : $site;
$description = (string) ($metaDescription ?? 'A fast, secure community Q&A platform.');
$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$canonicalUrl = (string) ($canonical ?? url($requestPath));
$ogType = (string) ($ogType ?? 'website');
$ogImage = isset($ogImage) ? (string) $ogImage : '';
$robotsContent = isset($robots) && is_string($robots) && trim($robots) !== '' ? trim($robots) : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1';
$schemaBlocks = [];
if (isset($schema) && is_string($schema) && trim($schema) !== '') {
    $schemaBlocks[] = $schema;
}
if (isset($schemas) && is_array($schemas)) {
    foreach ($schemas as $schemaBlock) {
        if (is_string($schemaBlock) && trim($schemaBlock) !== '') {
            $schemaBlocks[] = $schemaBlock;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?></title>
    <meta name="description" content="<?= h($description) ?>">
    <meta name="robots" content="<?= h($robotsContent) ?>">
    <link rel="canonical" href="<?= h($canonicalUrl) ?>">
    <?php if (!empty($pagination['previous'])): ?><link rel="prev" href="<?= h(url((string) $pagination['previous'])) ?>"><?php endif; ?>
    <?php if (!empty($pagination['next'])): ?><link rel="next" href="<?= h(url((string) $pagination['next'])) ?>"><?php endif; ?>
    <?php if (isset($authorName) && is_string($authorName) && trim($authorName) !== ''): ?><meta name="author" content="<?= h($authorName) ?>"><?php endif; ?>
    <?php if (isset($keywords) && is_string($keywords) && trim($keywords) !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
    <meta property="og:title" content="<?= h($pageTitle) ?>">
    <meta property="og:description" content="<?= h($description) ?>">
    <meta property="og:type" content="<?= h($ogType) ?>">
    <meta property="og:url" content="<?= h($canonicalUrl) ?>">
    <meta property="og:site_name" content="<?= h((string) $site) ?>">
    <meta name="twitter:title" content="<?= h($pageTitle) ?>">
    <meta name="twitter:description" content="<?= h($description) ?>">
    <?php if ($ogImage !== ''): ?>
        <meta property="og:image" content="<?= h($ogImage) ?>">
        <meta name="twitter:image" content="<?= h($ogImage) ?>">
    <?php endif; ?>
    <?php if ($ogType === 'article' && isset($publishedAt) && is_string($publishedAt) && strtotime($publishedAt) !== false): ?>
        <meta property="article:published_time" content="<?= h(date(DATE_ATOM, strtotime($publishedAt))) ?>">
    <?php endif; ?>
    <?php if ($ogType === 'article' && isset($modifiedAt) && is_string($modifiedAt) && strtotime($modifiedAt) !== false): ?>
        <meta property="article:modified_time" content="<?= h(date(DATE_ATOM, strtotime($modifiedAt))) ?>">
    <?php endif; ?>
    <?php if ($ogType === 'article' && isset($sectionName) && is_string($sectionName) && trim($sectionName) !== ''): ?>
        <meta property="article:section" content="<?= h($sectionName) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="<?= $ogImage !== '' ? 'summary_large_image' : 'summary' ?>">
    <link rel="preload" href="/public-assets/css/app.css" as="style">
    <link rel="stylesheet" href="/public-assets/css/app.css">
    <?php foreach ($schemaBlocks as $schemaBlock): ?>
        <script type="application/ld+json"><?= str_replace(['</', '<', '>'], ['\u003C/', '\u003C', '\u003E'], (string) $schemaBlock) ?></script>
    <?php endforeach; ?>
    <?php if (isset($securitySettings) && is_array($securitySettings) && setting_enabled($securitySettings, 'recaptcha_enabled') && trim((string) ($securitySettings['recaptcha_site_key'] ?? '')) !== ''): ?>
        <link rel="preconnect" href="https://www.google.com">
        <link rel="preconnect" href="https://www.gstatic.com" crossorigin>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>
<header class="site-header">
    <a class="brand" href="/"><?= h($site) ?></a>
    <form class="search" action="/search" role="search">
        <label class="sr-only" for="q">Search</label>
        <input id="q" name="q" value="<?= h($query ?? '') ?>" placeholder="Search questions">
    </form>
    <nav aria-label="Primary">
        <a href="/ask">Ask</a>
        <?php if (is_array($authUser)): ?>
            <a href="/user/<?= h((string) $authUser['username']) ?>"><?= h((string) $authUser['username']) ?></a>
            <a href="/notifications">Notifications</a>
            <a href="/settings/profile">Settings</a>
            <?php if (in_array(active_role($authUser), ['moderator', 'administrator'], true)): ?>
                <a href="/moderator">Moderate</a>
            <?php endif; ?>
            <?php if (active_role($authUser) === 'administrator'): ?>
                <a href="/admin">Admin</a>
            <?php endif; ?>
            <form method="post" action="/logout" class="inline">
                <?= $csrf->field('/logout') ?>
                <button type="submit">Sign out</button>
            </form>
        <?php else: ?>
            <a href="/login">Sign in</a>
            <a href="/register">Register</a>
        <?php endif; ?>
    </nav>
</header>
<main class="site-main">
    <?php foreach ($flash as $type => $messages): ?>
        <?php foreach ($messages as $message): ?>
            <p class="flash <?= h($type) ?>" role="status"><?= h($message) ?></p>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<footer class="site-footer">
    <a href="/sitemap.xml">Sitemap</a>
    <a href="/robots.txt">Robots</a>
    <a href="/llms.txt">llms.txt</a>
</footer>
<script src="/public-assets/js/app.js" defer></script>
</body>
</html>
