<?php

declare(strict_types=1);

$config = [
    'name' => getenv('APP_NAME') ?: 'Question And Answer Platform',
    'url' => getenv('APP_URL') ?: 'https://example.com',
    'environment' => getenv('APP_ENV') ?: 'production',
    'secure_cookies' => (getenv('APP_SECURE_COOKIES') ?: 'true') !== 'false',
    'csp' => "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: https:; font-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; upgrade-insecure-requests",
];

$local = __DIR__ . '/local.php';

if (is_file($local)) {
    $config = array_replace($config, (array) require $local);
}

return $config;
