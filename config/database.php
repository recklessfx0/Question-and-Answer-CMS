<?php

declare(strict_types=1);

$config = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_DATABASE') ?: 'qa_platform',
    'username' => getenv('DB_USERNAME') ?: 'qa_user',
    'password' => getenv('DB_PASSWORD') ?: 'change-me',
    'charset' => 'utf8mb4',
];

$local = __DIR__ . '/database.local.php';

if (is_file($local)) {
    $config = array_replace($config, (array) require $local);
}

return $config;
