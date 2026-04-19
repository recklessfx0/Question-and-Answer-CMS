<?php

declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function csp_nonce(): string
{
    return is_string($GLOBALS['csp_nonce'] ?? null) ? (string) $GLOBALS['csp_nonce'] : '';
}

function url(string $path = ''): string
{
    return site_url() . '/' . ltrim($path, '/');
}

function site_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $config = require BASE_PATH . '/config/app.php';
    $base = normalize_site_url((string) ($config['url'] ?? '')) ?: 'https://example.com';

    try {
        $databaseConfig = require BASE_PATH . '/config/database.php';
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $databaseConfig['host'],
            $databaseConfig['port'],
            $databaseConfig['database'],
            $databaseConfig['charset']
        );
        $pdo = new \PDO($dsn, (string) $databaseConfig['username'], (string) $databaseConfig['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $statement = $pdo->prepare('SELECT value FROM settings WHERE `key` = :key LIMIT 1');
        $statement->execute([':key' => 'site_url']);
        $configured = $statement->fetchColumn();
        $settingUrl = is_string($configured) ? normalize_site_url($configured) : '';
        if ($settingUrl !== '') {
            $base = $settingUrl;
        }
    } catch (\Throwable) {
        // Keep URL generation available during installation, migrations, or DB outages.
    }

    return $base;
}

function normalize_site_url(string $url): string
{
    $url = trim(str_replace("\0", '', $url));
    if ($url === '') {
        return '';
    }

    if (!str_contains($url, '://')) {
        $url = 'https://' . $url;
    }

    $validated = filter_var($url, FILTER_VALIDATE_URL);
    if (!is_string($validated) || $validated === '') {
        return '';
    }

    $parts = parse_url($validated);
    if (!is_array($parts)) {
        return '';
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return '';
    }

    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    $path = '/' . trim((string) ($parts['path'] ?? ''), '/');
    $path = $path === '/' ? '' : $path;

    return $scheme . '://' . $host . $port . $path;
}

function excerpt(string $text, int $length = 160): string
{
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
    return mb_strlen($plain) <= $length ? $plain : rtrim(mb_substr($plain, 0, $length - 1)) . '...';
}

function xml_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
}

function safe_external_url(string $url): string
{
    $url = trim(str_replace("\0", '', $url));
    if ($url === '') {
        return '';
    }

    $validated = filter_var($url, FILTER_VALIDATE_URL);
    if (!is_string($validated) || $validated === '') {
        return '';
    }

    $scheme = strtolower((string) parse_url($validated, PHP_URL_SCHEME));

    return in_array($scheme, ['http', 'https'], true) ? $validated : '';
}

function safe_internal_path(string $url, string $default = ''): string
{
    $url = trim(str_replace("\0", '', $url));
    if ($url === '') {
        return $default;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return $default;
    }

    if (
        isset($parts['scheme'])
        || isset($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['port'])
    ) {
        return $default;
    }

    $path = (string) ($parts['path'] ?? '');
    if ($path === '' || !str_starts_with($path, '/')) {
        return $default;
    }

    $clean = $path;
    if (isset($parts['query']) && $parts['query'] !== '') {
        $clean .= '?' . (string) $parts['query'];
    }
    if (isset($parts['fragment']) && $parts['fragment'] !== '') {
        $clean .= '#' . (string) $parts['fragment'];
    }

    return $clean;
}

function is_duplicate_key_exception(\Throwable $e): bool
{
    if (!$e instanceof \PDOException) {
        return false;
    }

    $code = (string) $e->getCode();
    if ($code === '23000') {
        return true;
    }

    $message = mb_strtolower($e->getMessage());

    return str_contains($message, 'duplicate')
        || str_contains($message, '1062')
        || str_contains($message, 'unique');
}

function active_role(?array $user): string
{
    return is_array($user) ? (string) ($user['role'] ?? 'guest') : 'guest';
}

/** @param array<string, string> $attributes */
function uploaded_image(string $path, string $class = '', string $alt = '', array $attributes = []): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }

    $pathOnly = (string) (parse_url($path, PHP_URL_PATH) ?: $path);
    $alt = trim($alt) !== '' ? trim($alt) : media_alt_text($pathOnly);
    $classAttribute = $class !== '' ? ' class="' . h($class) . '"' : '';
    $attributeHtml = '';
    foreach ($attributes as $name => $value) {
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9:-]*$/', (string) $name) !== 1) {
            continue;
        }
        $attributeHtml .= ' ' . $name . '="' . h($value) . '"';
    }

    $dimensions = image_dimensions_attributes($pathOnly);
    $image = '<img' . $classAttribute . ' src="' . h($pathOnly) . '" alt="' . h($alt) . '"' . $dimensions . ' loading="lazy" decoding="async"' . $attributeHtml . '>';
    $extension = strtolower((string) pathinfo($pathOnly, PATHINFO_EXTENSION));

    if (!str_starts_with($pathOnly, '/public-assets/images/')) {
        return $image;
    }

    if ($extension === 'avif') {
        $fallback = substr($pathOnly, 0, -5) . '.webp';
        return '<picture><source srcset="' . h($pathOnly) . '" type="image/avif"><source srcset="' . h($fallback) . '" type="image/webp">' . $image . '</picture>';
    }

    if ($extension === 'webp') {
        return '<picture><source srcset="' . h($pathOnly) . '" type="image/webp">' . $image . '</picture>';
    }

    return $image;
}

/** @return array<int, string> */
function media_paths_from_markdown(string $body, bool $imagesOnly = false): array
{
    preg_match_all('/(?:!\[[^\]]*\]|\[uploaded video\])\((\/public-assets\/images\/[a-zA-Z0-9\/._-]+)\)/', $body, $matches);
    $paths = [];
    foreach ($matches[1] ?? [] as $path) {
        $extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));
        $isImage = in_array($extension, ['avif', 'webp', 'jpg', 'jpeg', 'png', 'gif'], true);
        if ($imagesOnly && !$isImage) {
            continue;
        }
        $paths[] = (string) $path;
    }

    return array_values(array_unique($paths));
}

function media_alt_text(string $path): string
{
    $name = strtolower((string) pathinfo($path, PATHINFO_FILENAME));
    $name = preg_replace('/[a-f0-9]{16,}/', '', $name) ?? '';
    $name = trim((string) preg_replace('/[^a-z0-9]+/', ' ', $name));

    return $name !== '' ? ucwords($name) : 'Uploaded image';
}

function image_dimensions_attributes(string $path): string
{
    if (!str_starts_with($path, '/public-assets/images/')) {
        return '';
    }

    $file = BASE_PATH . $path;
    if (!is_file($file)) {
        return '';
    }

    $size = @getimagesize($file);
    if (!is_array($size) || (int) ($size[0] ?? 0) < 1 || (int) ($size[1] ?? 0) < 1) {
        return '';
    }

    return ' width="' . (int) $size[0] . '" height="' . (int) $size[1] . '"';
}

/** @param array<string, string> $settings */
function setting_enabled(array $settings, string $key): bool
{
    return in_array(strtolower((string) ($settings[$key] ?? '')), ['1', 'true', 'yes', 'on'], true);
}

/** @param array<string, string> $settings */
function recaptcha_widget(array $settings): string
{
    $siteKey = trim((string) ($settings['recaptcha_site_key'] ?? ''));
    if (!setting_enabled($settings, 'recaptcha_enabled') || $siteKey === '') {
        return '';
    }

    return '<div class="recaptcha-wrap"><div class="g-recaptcha" data-sitekey="' . h($siteKey) . '"></div></div>';
}
