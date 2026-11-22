<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80500) {
    http_response_code(503);
    exit('This CMS requires PHP 8.5 or newer.');
}

ini_set('display_errors', '0');

define('BASE_PATH', __DIR__);
define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');
define('APP_START', microtime(true));

require BASE_PATH . '/core/Autoloader.php';

\Core\Autoloader::register([
    'Core\\' => BASE_PATH . '/core',
    'App\\' => BASE_PATH . '/app',
]);

require BASE_PATH . '/app/Helpers/helpers.php';

$config = require BASE_PATH . '/config/app.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $assetPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if ($assetPath === '/service-worker.js') {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Content-Type-Options: nosniff');
        readfile(BASE_PATH . '/public-assets/js/service-worker.js');
        return;
    }
    if ($assetPath === '/manifest.webmanifest') {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=300');
        echo json_encode([
            'id' => '/',
            'name' => (string) $config['name'],
            'short_name' => 'Q&A',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'theme_color' => '#F7F9F6',
            'background_color' => '#F7F9F6',
            'icons' => [
                ['src' => '/public-assets/images/app-icon.svg', 'type' => 'image/svg+xml', 'sizes' => 'any', 'purpose' => 'any maskable'],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        return;
    }
}
$databaseConfig = require BASE_PATH . '/config/database.php';
$routes = require BASE_PATH . '/config/routes.php';

$container = new \Core\Container();
$container->set(\Core\Container::class, static fn (\Core\Container $c): \Core\Container => $c);
$container->set('config', static fn (): array => $config);
$container->set('db.config', static fn (): array => $databaseConfig);
$container->set(\Core\Database::class, static fn (\Core\Container $c): \Core\Database => new \Core\Database($c->get('db.config')));
$container->set(\App\Models\SettingsModel::class, static fn (\Core\Container $c): \App\Models\SettingsModel => new \App\Models\SettingsModel($c->get(\Core\Database::class), requestScoped: true));
$container->set(\Core\View::class, static fn (\Core\Container $c): \Core\View => new \Core\View(BASE_PATH . '/views', $c));
$container->set(\Core\Cache::class, static fn (): \Core\Cache => new \Core\Cache(STORAGE_PATH . '/cache'));
$container->set(\Core\PageCache::class, static fn (): \Core\PageCache => new \Core\PageCache(STORAGE_PATH . '/cache/pages'));
$container->set(\Core\Logger::class, static fn (): \Core\Logger => new \Core\Logger(STORAGE_PATH . '/logs/app.log'));
$container->set(\Core\Queue::class, static fn (): \Core\Queue => new \Core\Queue(STORAGE_PATH . '/queue'));
$container->set(\App\Services\MailService::class, static fn (\Core\Container $c): \App\Services\MailService => new \App\Services\MailService($c->get(\App\Models\SettingsModel::class), $c->get('config')));
$container->set(\Core\Session::class, static fn (): \Core\Session => new \Core\Session(STORAGE_PATH . '/sessions', (bool) ($config['secure_cookies'] ?? true)));
$container->set(\Core\Csrf::class, static fn (\Core\Container $c): \Core\Csrf => new \Core\Csrf($c->get(\Core\Session::class)));
$container->set(\Core\RateLimiter::class, static fn (): \Core\RateLimiter => new \Core\RateLimiter(STORAGE_PATH . '/cache/rate-limits'));

site_url($container->get(\App\Models\SettingsModel::class));

$router = new \Core\Router();
$routes($router);

$kernel = new \Core\Kernel($container, $router);
$kernel->handle(\Core\Request::capture())->send();
