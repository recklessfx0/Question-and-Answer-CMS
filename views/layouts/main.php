<?php

declare(strict_types=1);

$appConfig = require BASE_PATH . '/config/app.php';
$site = trim((string) ($securitySettings['site_title'] ?? '')) ?: (string) $appConfig['name'];
$defaultTitle = trim((string) ($securitySettings['default_meta_title'] ?? '')) ?: $site;
$pageTitle = isset($title) ? (string) $title . ' | ' . $site : $defaultTitle;
$defaultDescription = trim((string) ($securitySettings['default_meta_description'] ?? '')) ?: (trim((string) ($securitySettings['site_description'] ?? '')) ?: 'A fast, secure community question and answer platform.');
$description = (string) ($metaDescription ?? $defaultDescription);
$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$baseCanonicalUrl = (string) ($canonical ?? url($requestPath));
$defaultLanguage = default_language();
$currentLanguage = current_language();
$canonicalUrl = localized_url($baseCanonicalUrl, $currentLanguage, $defaultLanguage);
$alternateLanguages = [];
foreach (active_languages() as $language) {
    $code = strtolower(trim((string) ($language['code'] ?? '')));
    if ($code === '') {
        continue;
    }
    $alternateLanguages[$code] = localized_url($baseCanonicalUrl, $code, $defaultLanguage);
}
$ogType = (string) ($ogType ?? 'website');
$defaultOgTitle = trim((string) ($securitySettings['default_og_title'] ?? ''));
$defaultOgDescription = trim((string) ($securitySettings['default_og_description'] ?? ''));
$hasPageTitle = isset($title) && trim((string) $title) !== '';
$hasMetaDescription = isset($metaDescription) && trim((string) $metaDescription) !== '';
$ogTitleContent = isset($ogTitle) ? (string) $ogTitle : ($hasPageTitle ? $pageTitle : ($defaultOgTitle !== '' ? $defaultOgTitle : $pageTitle));
$ogDescriptionContent = isset($ogDescription) ? (string) $ogDescription : ($hasMetaDescription ? $description : ($defaultOgDescription !== '' ? $defaultOgDescription : $description));
$defaultOgImagePath = safe_internal_path((string) (($securitySettings['default_og_image_path'] ?? '') ?: ''));
if ($defaultOgImagePath !== '' && !str_starts_with($defaultOgImagePath, '/public-assets/images/social/')) {
    $defaultOgImagePath = '';
}
$ogImage = isset($ogImage) ? (string) $ogImage : ($defaultOgImagePath !== '' ? url($defaultOgImagePath) : '');
$ogImageWidth = 0;
$ogImageHeight = 0;
$ogImagePath = $ogImage !== '' ? safe_internal_path((string) (parse_url($ogImage, PHP_URL_PATH) ?: '')) : '';
if ($ogImagePath !== '' && str_starts_with($ogImagePath, '/public-assets/images/')) {
    $ogImageSize = is_file(BASE_PATH . $ogImagePath) ? @getimagesize(BASE_PATH . $ogImagePath) : false;
    if (is_array($ogImageSize)) {
        $ogImageWidth = (int) ($ogImageSize[0] ?? 0);
        $ogImageHeight = (int) ($ogImageSize[1] ?? 0);
    }
}
$ogImageAlt = trim((string) ($ogImageAlt ?? '')) ?: $ogTitleContent;
$robotsContent = isset($robots) && is_string($robots) && trim($robots) !== '' ? trim($robots) : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1';
$siteLogoPath = safe_internal_path((string) (($securitySettings['site_logo_path'] ?? '') ?: ''));
if ($siteLogoPath !== '' && !str_starts_with($siteLogoPath, '/public-assets/images/logos/')) {
    $siteLogoPath = '';
}
$faviconPath = safe_internal_path((string) (($securitySettings['site_favicon_path'] ?? '') ?: ''));
if ($faviconPath !== '' && !str_starts_with($faviconPath, '/public-assets/images/favicons/')) {
    $faviconPath = '';
}
$assetUrl = static function (string $path): string {
    $file = BASE_PATH . $path;
    $version = is_file($file) ? (string) filemtime($file) : '1';

    return $path . '?v=' . rawurlencode($version);
};
$schemaBlocks = [];
if (isset($siteSchema) && is_string($siteSchema) && trim($siteSchema) !== '') {
    $schemaBlocks[] = $siteSchema;
}
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
<html lang="<?= h(current_language()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?></title>
    <meta name="description" content="<?= h($description) ?>">
    <meta name="robots" content="<?= h($robotsContent) ?>">
    <link rel="canonical" href="<?= h($canonicalUrl) ?>">
    <?php foreach ($alternateLanguages as $languageCode => $languageUrl): ?>
        <link rel="alternate" hreflang="<?= h($languageCode) ?>" href="<?= h($languageUrl) ?>">
    <?php endforeach; ?>
    <?php if ($alternateLanguages !== []): ?>
        <link rel="alternate" hreflang="x-default" href="<?= h(localized_url($baseCanonicalUrl, $defaultLanguage, $defaultLanguage)) ?>">
    <?php endif; ?>
    <?php if (!empty($pagination['previous'])): ?><link rel="prev" href="<?= h(localized_url((string) $pagination['previous'], $currentLanguage, $defaultLanguage)) ?>"><?php endif; ?>
    <?php if (!empty($pagination['next'])): ?><link rel="next" href="<?= h(localized_url((string) $pagination['next'], $currentLanguage, $defaultLanguage)) ?>"><?php endif; ?>
    <?php if (isset($authorName) && is_string($authorName) && trim($authorName) !== ''): ?><meta name="author" content="<?= h($authorName) ?>"><?php endif; ?>
    <?php if (isset($keywords) && is_string($keywords) && trim($keywords) !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
    <meta property="og:title" content="<?= h($ogTitleContent) ?>">
    <meta property="og:description" content="<?= h($ogDescriptionContent) ?>">
    <meta property="og:type" content="<?= h($ogType) ?>">
    <meta property="og:url" content="<?= h($canonicalUrl) ?>">
    <meta property="og:site_name" content="<?= h((string) $site) ?>">
    <meta name="twitter:title" content="<?= h($ogTitleContent) ?>">
    <meta name="twitter:description" content="<?= h($ogDescriptionContent) ?>">
    <?php if (trim((string) ($securitySettings['twitter_site'] ?? '')) !== ''): ?><meta name="twitter:site" content="<?= h((string) $securitySettings['twitter_site']) ?>"><?php endif; ?>
    <?php if (trim((string) ($securitySettings['twitter_creator'] ?? '')) !== ''): ?><meta name="twitter:creator" content="<?= h((string) $securitySettings['twitter_creator']) ?>"><?php endif; ?>
    <?php if ($ogImage !== ''): ?>
        <meta property="og:image" content="<?= h($ogImage) ?>">
        <?php if ($ogImageWidth > 0 && $ogImageHeight > 0): ?>
            <meta property="og:image:width" content="<?= $ogImageWidth ?>">
            <meta property="og:image:height" content="<?= $ogImageHeight ?>">
        <?php endif; ?>
        <meta property="og:image:alt" content="<?= h($ogImageAlt) ?>">
        <meta name="twitter:image" content="<?= h($ogImage) ?>">
        <meta name="twitter:image:alt" content="<?= h($ogImageAlt) ?>">
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
    <?php if ($faviconPath !== ''): ?><link rel="icon" href="<?= h($faviconPath) ?>"><?php endif; ?>
    <link rel="preload" href="<?= h($assetUrl('/public-assets/css/app.css')) ?>" as="style">
    <link rel="stylesheet" href="<?= h($assetUrl('/public-assets/css/app.css')) ?>">
    <?php foreach ($schemaBlocks as $schemaBlock): ?>
        <script type="application/ld+json" nonce="<?= h(csp_nonce()) ?>"><?= str_replace(['</', '<', '>'], ['\u003C/', '\u003C', '\u003E'], (string) $schemaBlock) ?></script>
    <?php endforeach; ?>
    <?php if (isset($securitySettings) && is_array($securitySettings) && setting_enabled($securitySettings, 'recaptcha_enabled') && trim((string) ($securitySettings['recaptcha_site_key'] ?? '')) !== ''): ?>
        <link rel="preconnect" href="https://www.google.com">
        <link rel="preconnect" href="https://www.gstatic.com" crossorigin>
        <script src="https://www.google.com/recaptcha/api.js" nonce="<?= h(csp_nonce()) ?>" async defer></script>
    <?php endif; ?>
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= h(localized_path('/')) ?>">
        <?php if ($siteLogoPath !== ''): ?>
            <?= uploaded_image($siteLogoPath, 'site-logo', (string) $site) ?>
        <?php endif; ?>
        <span><?= h($site) ?></span>
    </a>
    <form class="search" action="<?= h(localized_path('/search')) ?>" role="search">
        <label class="sr-only" for="q"><?= h(t('layout.search.label', 'Search')) ?></label>
        <input id="q" name="q" value="<?= h($query ?? '') ?>" placeholder="<?= h(t('layout.search.placeholder', 'Search questions')) ?>">
    </form>
    <nav aria-label="Primary">
        <a href="<?= h(localized_path('/ask')) ?>"><?= h(t('layout.nav.ask', 'Ask')) ?></a>
        <?php if (is_array($authUser)): ?>
            <a href="<?= h(localized_path('/user/' . (string) $authUser['username'])) ?>"><?= h((string) $authUser['username']) ?></a>
            <a href="<?= h(localized_path('/notifications')) ?>"><?= h(t('layout.nav.notifications', 'Notifications')) ?></a>
            <a href="<?= h(localized_path('/settings/profile')) ?>"><?= h(t('layout.nav.settings', 'Settings')) ?></a>
            <?php if (in_array(active_role($authUser), ['moderator', 'administrator'], true)): ?>
                <a href="<?= h(localized_path('/moderator')) ?>"><?= h(t('layout.nav.moderate', 'Moderate')) ?></a>
            <?php endif; ?>
            <?php if (active_role($authUser) === 'administrator'): ?>
                <a href="<?= h(localized_path('/admin')) ?>"><?= h(t('layout.nav.admin', 'Admin')) ?></a>
            <?php endif; ?>
            <form method="post" action="<?= h(localized_path('/logout')) ?>" class="inline">
                <?= $csrf->field('/logout') ?>
                <button type="submit"><?= h(t('layout.nav.sign_out', 'Sign out')) ?></button>
            </form>
        <?php else: ?>
            <a href="<?= h(localized_path('/login')) ?>"><?= h(t('layout.nav.sign_in', 'Sign in')) ?></a>
            <a href="<?= h(localized_path('/register')) ?>"><?= h(t('layout.nav.register', 'Register')) ?></a>
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
    <nav class="footer-links" aria-label="<?= h(t('layout.footer.discovery', 'Discovery links')) ?>">
        <a href="<?= h(url('/sitemap.xml')) ?>"><?= h(t('layout.footer.sitemap', 'Sitemap')) ?></a>
        <a href="<?= h(url('/robots.txt')) ?>"><?= h(t('layout.footer.robots', 'Robots')) ?></a>
        <a href="<?= h(url('/llms.txt')) ?>">llms.txt</a>
        <a href="<?= h(url('/llms-full.txt')) ?>">llms-full.txt</a>
    </nav>
    <nav class="footer-links" aria-label="<?= h(t('layout.footer.info', 'Site information')) ?>">
        <a href="<?= h(localized_path('/about')) ?>"><?= h(t('layout.footer.about', 'About')) ?></a>
        <a href="<?= h(localized_path('/contact')) ?>"><?= h(t('layout.footer.contact', 'Contact')) ?></a>
        <a href="<?= h(localized_path('/privacy')) ?>"><?= h(t('layout.footer.privacy', 'Privacy')) ?></a>
        <a href="<?= h(localized_path('/terms')) ?>"><?= h(t('layout.footer.terms', 'Terms')) ?></a>
        <a href="<?= h(localized_path('/community-guidelines')) ?>"><?= h(t('layout.footer.guidelines', 'Guidelines')) ?></a>
    </nav>
    <p class="footer-copy">&copy; <?= h(date('Y')) ?> <?= h((string) $site) ?>. <?= h(t('layout.footer.copyright', 'All rights reserved.')) ?></p>
</footer>
<script src="<?= h($assetUrl('/public-assets/js/app.js')) ?>" nonce="<?= h(csp_nonce()) ?>" defer></script>
</body>
</html>
