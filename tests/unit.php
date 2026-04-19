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

$slugger = new \Core\Slugger();
$assert($slugger->slug('Hello Secure PHP!') === 'hello-secure-php', 'Slugger should normalize titles.');

$markdown = new \Core\Markdown();
$html = $markdown->toHtml('<script>alert(1)</script> **safe**');
$assert(!str_contains($html, '<script>'), 'Markdown should escape scripts.');
$assert(str_contains($html, '<strong>safe</strong>'), 'Markdown should render bold text.');
$imageHtml = $markdown->toHtml('![alt](/public-assets/images/uploads/demo.webp)');
$assert(str_contains($imageHtml, '<img src="/public-assets/images/uploads/demo.webp"'), 'Markdown should render local uploaded images.');
$mentionAltHtml = $markdown->toHtml('![hi @alice](/public-assets/images/uploads/demo.webp)');
$assert(str_contains($mentionAltHtml, 'alt="hi @alice"'), 'Markdown should not render mentions inside image alt text.');

$validator = (new \Core\Validator())->email('email', 'not-an-email');
$assert(!$validator->passes(), 'Validator should reject invalid email.');

$queueDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'q2a-queue-test-' . bin2hex(random_bytes(4));
$queue = new \Core\Queue($queueDir);
$queue->push('ExampleJob', ['value' => 1]);
$job = $queue->pop();
$assert(is_array($job), 'Queue should reserve a pending job.');
$assert(($job['job'] ?? null) === 'ExampleJob', 'Queue should preserve the job name.');
$queue->release(is_array($job) ? $job : [], null, 0);
$retried = $queue->pop();
$assert(is_array($retried), 'Released queue jobs should become available again.');
$assert((int) ($retried['attempts'] ?? 0) >= 1, 'Released jobs should increment their attempt count.');
if (is_array($retried)) {
    $queue->complete($retried);
}
$assert($queue->pop() === null, 'Completed queue jobs should be removed.');

$cleanup = static function (string $path) use (&$cleanup): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $target = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($target)) {
            $cleanup($target);
            continue;
        }

        @unlink($target);
    }

    @rmdir($path);
};
$cleanup($queueDir);


echo $failures === 0 ? "OK {$tests} tests\n" : "{$failures} of {$tests} tests failed\n";
exit($failures === 0 ? 0 : 1);
