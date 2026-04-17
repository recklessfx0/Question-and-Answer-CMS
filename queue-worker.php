<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);
define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');

require BASE_PATH . '/core/Autoloader.php';
\Core\Autoloader::register(['Core\\' => BASE_PATH . '/core', 'App\\' => BASE_PATH . '/app']);
require BASE_PATH . '/app/Helpers/helpers.php';

$queue = new \Core\Queue(STORAGE_PATH . '/queue');
$logger = new \Core\Logger(STORAGE_PATH . '/logs/queue.log');
$config = require BASE_PATH . '/config/app.php';
$database = new \Core\Database(require BASE_PATH . '/config/database.php');
$notifications = new \App\Models\NotificationModel($database);

$sendMail = static function (string $to, string $subject, string $body) use ($config): void {
    $headers = [
        'From: ' . (string) $config['name'] . ' <no-reply@' . parse_url((string) $config['url'], PHP_URL_HOST) . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    if (!mail($to, $subject, $body, implode("\r\n", $headers))) {
        throw new RuntimeException('Mail transport rejected the message.');
    }
};

$redactedJob = static function (array $job): array {
    $copy = $job;
    unset($copy['__queue_file']);

    $payload = is_array($copy['payload'] ?? null) ? $copy['payload'] : [];
    foreach (['token', 'email'] as $sensitiveKey) {
        if (array_key_exists($sensitiveKey, $payload)) {
            $payload[$sensitiveKey] = '[redacted]';
        }
    }
    $copy['payload'] = $payload;

    return $copy;
};

while (true) {
    $job = $queue->pop();
    if ($job === null) {
        sleep(1);
        continue;
    }

    try {
        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $name = (string) ($job['job'] ?? '');

        if ($name === 'SendEmailVerification') {
            $link = rtrim((string) $config['url'], '/') . '/email/verify/' . rawurlencode((string) $payload['token']);
            $sendMail((string) $payload['email'], 'Verify your email', "Open this link to verify your email:\n\n{$link}");
            $notifications->create((int) $payload['user_id'], 'Email verification', ['message' => 'Verification email queued.', 'url' => '/settings/profile']);
        }

        if ($name === 'SendPasswordReset') {
            $link = rtrim((string) $config['url'], '/') . '/reset-password/' . rawurlencode((string) $payload['token']);
            $sendMail((string) $payload['email'], 'Reset your password', "Open this link to reset your password:\n\n{$link}");
        }

        if ($name === 'AnswerPostedNotification') {
            $question = $database->fetch('SELECT user_id, slug, title FROM questions WHERE id = :id LIMIT 1', [':id' => (int) $payload['question_id']]);
            if ($question !== null) {
                $notifications->create((int) $question['user_id'], 'New answer', ['message' => 'A new answer was posted on ' . (string) $question['title'], 'url' => '/question/' . (string) $question['slug']]);
            }
        }

        if ($name === 'NotifyModerators') {
            foreach ($database->fetchAll('SELECT id FROM users WHERE role IN ("moderator", "administrator")') as $moderator) {
                $notifications->create((int) $moderator['id'], 'New question', ['message' => 'A new question was posted.', 'url' => '/moderator']);
            }
        }

        // Log before completing so a log-write failure surfaces as a retry rather than silent loss
        file_put_contents(STORAGE_PATH . '/logs/queue.log', '[' . date(DATE_ATOM) . '] processed ' . json_encode($redactedJob($job), JSON_THROW_ON_ERROR) . PHP_EOL, FILE_APPEND | LOCK_EX);
        $queue->complete($job);
    } catch (\Throwable $e) {
        $queue->release($job, $e->getMessage());
        $logger->error($e);
    }
}
