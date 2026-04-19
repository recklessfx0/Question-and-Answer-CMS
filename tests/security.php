<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/core/Autoloader.php';
\Core\Autoloader::register(['Core\\' => BASE_PATH . '/core', 'App\\' => BASE_PATH . '/app']);
require BASE_PATH . '/app/Helpers/helpers.php';

$tests = 0;
$failures = 0;

$assert = static function (bool $condition, string $message) use (&$tests, &$failures): void {
    $tests++;
    if (!$condition) {
        $failures++;
        echo "FAIL: {$message}\n";
    }
};

$twoFactor = new \App\Services\TwoFactorService();
$secret = $twoFactor->generateSecret();
$assert(preg_match('/^[A-Z2-7]{32}$/', $secret) === 1, 'TOTP secret should be base32.');
$assert(!$twoFactor->verify($secret, '000000'), 'Wrong TOTP codes should fail.');
$assert(str_starts_with($twoFactor->provisioningUri('Example', 'admin@example.com', $secret), 'otpauth://totp/'), 'TOTP provisioning URI should be generated.');

$assert(normalize_site_url('example.com/path/') === 'https://example.com/path', 'Site URL should normalize bare domains.');
$assert(normalize_site_url('https://Example.com/path/?query=ignored') === 'https://example.com/path', 'Site URL should drop query strings and lowercase host.');
$assert(normalize_site_url('javascript:alert(1)') === '', 'Site URL should reject non-http schemes.');

$rateDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'q2a-rate-test-' . bin2hex(random_bytes(4));
$limiter = new \Core\RateLimiter($rateDir);
$assert(!$limiter->hitAndCheck('login', 'user@example.com', 2, 60), 'First login attempt should pass rate limiter.');
$assert(!$limiter->hitAndCheck('login', 'user@example.com', 2, 60), 'Second login attempt should pass rate limiter.');
$assert($limiter->hitAndCheck('login', 'user@example.com', 2, 60), 'Third login attempt should be blocked.');
$assert($limiter->attemptCount('login', 'user@example.com', 60) === 3, 'Rate limiter should report current attempt count.');

$cleanup = static function (string $path) use (&$cleanup): void {
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $target = $path . DIRECTORY_SEPARATOR . $item;
        is_dir($target) ? $cleanup($target) : @unlink($target);
    }
    @rmdir($path);
};
$cleanup($rateDir);

echo $failures === 0 ? "OK {$tests} security tests\n" : "{$failures} of {$tests} security tests failed\n";
exit($failures === 0 ? 0 : 1);
