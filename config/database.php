<?php

declare(strict_types=1);

$appEnv    = getenv('APP_ENV') ?: 'production';
$dbPass    = getenv('DB_PASSWORD');
$localFile = __DIR__ . '/database.local.php';

if ($dbPass === false && !is_file($localFile) && $appEnv !== 'development') {
    throw new RuntimeException('DB_PASSWORD environment variable is not set. Refusing to start with insecure defaults.');
}

$config = [
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => (int) (getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_DATABASE') ?: 'qa_platform',
    'username' => getenv('DB_USERNAME') ?: 'qa_user',
    'password' => $dbPass !== false ? $dbPass : 'change-me',
    'charset'  => 'utf8mb4',
];

if (is_file($localFile)) {
    $config = array_replace($config, (array) require $localFile);
}

return $config;
