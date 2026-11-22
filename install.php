<?php

declare(strict_types=1);

use App\Models\UserModel;
use Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (in_array('--help', $argv, true)) {
    fwrite(STDOUT, "Usage: php install.php <admin-username> <admin-email>\nSet CMS_INSTALL_PASSWORD through the process environment.\n");
    exit;
}

$extensions = ['pdo_mysql', 'mbstring', 'fileinfo', 'openssl', 'simplexml', 'iconv'];
$missing = array_filter($extensions, static fn (string $extension): bool => !extension_loaded($extension));
if (PHP_VERSION_ID < 80500 || $missing !== [] || !defined('PASSWORD_ARGON2ID')) {
    fwrite(STDERR, "Installation requires PHP 8.5+, Argon2id, and these extensions: " . implode(', ', $extensions) . ".\n");
    exit(1);
}

require __DIR__ . '/core/Autoloader.php';
\Core\Autoloader::register(['Core\\' => __DIR__ . '/core', 'App\\' => __DIR__ . '/app']);
require __DIR__ . '/app/Helpers/helpers.php';

try {
    $appConfig = require __DIR__ . '/config/app.php';
    $databaseConfig = require __DIR__ . '/config/database.php';
    foreach (['logs', 'cache', 'sessions', 'queue'] as $directory) {
        $path = __DIR__ . '/storage/' . $directory;
        if ((!is_dir($path) && !@mkdir($path, 0750, true) && !is_dir($path)) || !is_writable($path)) {
            throw new RuntimeException("Cannot write to storage/{$directory}.");
        }
    }

    $db = new Database($databaseConfig);
    $lockName = 'qa-install-' . substr(hash('sha256', (string) $databaseConfig['database']), 0, 40);
    $lockParameters = [':name' => $lockName];
    $lock = $db->fetch('SELECT GET_LOCK(:name, 5) AS acquired', $lockParameters);
    if ((int) ($lock['acquired'] ?? 0) !== 1) {
        throw new RuntimeException('Another installation is running. Retry after it finishes.');
    }

    try {
        $schema = file_get_contents(__DIR__ . '/database/schema.sql');
        if ($schema === false || !preg_match_all('/^CREATE TABLE (\w+) /m', $schema, $tables)) {
            throw new RuntimeException('The bundled schema could not be read.');
        }

        $existing = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if ($existing !== []) {
            if (array_diff($tables[1], $existing) !== [] || $db->fetch('SELECT id FROM languages LIMIT 1') === null) {
                throw new RuntimeException('The database contains an incomplete or older schema. Restore or migrate it, or use a fresh empty database.');
            }
            if ($db->fetch("SELECT id FROM users WHERE role = 'administrator' AND banned_at IS NULL AND deleted_at IS NULL LIMIT 1") !== null) {
                fwrite(STDOUT, "The CMS is already installed and has an active administrator.\n");
                return;
            }
        }

        $username = trim($argv[1] ?? '');
        $email = trim($argv[2] ?? '');
        $password = getenv('CMS_INSTALL_PASSWORD');
        if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $username) || strlen($username) < 3 || strlen($username) > 40
            || strlen($email) > 180 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || $password === false || mb_strlen($password) < 12 || mb_strlen($password) > 128) {
            throw new RuntimeException('Supply a valid username, email, and CMS_INSTALL_PASSWORD of 12 to 128 characters. See php install.php --help.');
        }

        $siteUrl = null;
        if ($existing === []) {
            $siteUrl = normalize_site_url((string) $appConfig['url']);
            if ($siteUrl === '') {
                throw new RuntimeException('Set APP_URL to the site’s HTTP or HTTPS address.');
            }
            // Strip the legacy CREATE DATABASE and USE statements. PDO already selected the configured database.
            $statements = preg_split('/;\h*(?:\R|$)/', $schema, -1, PREG_SPLIT_NO_EMPTY);
            foreach (array_slice($statements, 2) as $statement) {
                if (trim($statement) !== '') {
                    $db->pdo()->exec($statement);
                }
            }
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);
        $users = new UserModel($db);
        $db->transaction(static function () use ($db, $users, $username, $email, $hash, $appConfig, $siteUrl): void {
            $id = $users->create($username, $email, $hash, 'administrator');
            $users->markEmailVerified($id);
            if ($siteUrl !== null) {
                $settings = new \App\Models\SettingsModel($db);
                $settings->set('site_url', $siteUrl);
                $settings->set('site_title', (string) $appConfig['name']);
            }
        });
        fwrite(STDOUT, "Installation complete. Sign in with the administrator account.\n");
    } finally {
        $db->fetch('SELECT RELEASE_LOCK(:name)', $lockParameters);
    }
} catch (PDOException $exception) {
    fwrite(STDERR, "Database setup failed (SQLSTATE {$exception->getCode()}). Check the database settings, grants, and administrator username or email. If schema import was interrupted, inspect the database before retrying.\n");
    exit(1);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
