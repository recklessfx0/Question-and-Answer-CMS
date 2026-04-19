<?php

declare(strict_types=1);

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
$databaseConfig = require BASE_PATH . '/config/database.php';
$routes = require BASE_PATH . '/config/routes.php';

$container = new \Core\Container();
$container->set(\Core\Container::class, static fn (\Core\Container $c): \Core\Container => $c);
$container->set('config', static fn (): array => $config);
$container->set('db.config', static fn (): array => $databaseConfig);
$container->set(\Core\Database::class, static fn (\Core\Container $c): \Core\Database => new \Core\Database($c->get('db.config')));
$container->set(\Core\View::class, static fn (\Core\Container $c): \Core\View => new \Core\View(BASE_PATH . '/views', $c));
$container->set(\Core\Cache::class, static fn (): \Core\Cache => new \Core\Cache(STORAGE_PATH . '/cache'));
$container->set(\Core\PageCache::class, static fn (): \Core\PageCache => new \Core\PageCache(STORAGE_PATH . '/cache/pages'));
$container->set(\Core\Logger::class, static fn (): \Core\Logger => new \Core\Logger(STORAGE_PATH . '/logs/app.log'));
$container->set(\Core\Queue::class, static fn (): \Core\Queue => new \Core\Queue(STORAGE_PATH . '/queue'));
$container->set(\App\Services\MailService::class, static fn (\Core\Container $c): \App\Services\MailService => new \App\Services\MailService($c->get(\App\Models\SettingsModel::class), $c->get('config')));
$container->set(\Core\Session::class, static fn (): \Core\Session => new \Core\Session(STORAGE_PATH . '/sessions', (bool) ($config['secure_cookies'] ?? true)));
$container->set(\Core\Csrf::class, static fn (\Core\Container $c): \Core\Csrf => new \Core\Csrf($c->get(\Core\Session::class)));
$container->set(\Core\RateLimiter::class, static fn (): \Core\RateLimiter => new \Core\RateLimiter(STORAGE_PATH . '/cache/rate-limits'));

$router = new \Core\Router();
$routes($router);

$kernel = new \Core\Kernel($container, $router);
$kernel->handle(\Core\Request::capture())->send();
