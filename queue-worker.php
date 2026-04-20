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
$users = new \App\Models\UserModel($database);
$mail = new \App\Services\MailService(new \App\Models\SettingsModel($database), $config);

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

$isStaleNotification = static function (array $job): bool {
    $name = (string) ($job['job'] ?? '');
    $ttlByJob = [
        'AnswerPostedNotification' => 172800,
        'NotifyModerators' => 86400,
        'MentionNotification' => 172800,
    ];
    if (!isset($ttlByJob[$name])) {
        return false;
    }

    $createdAt = strtotime((string) ($job['created_at'] ?? ''));
    if ($createdAt === false) {
        $file = (string) ($job['__queue_file'] ?? '');
        $mtime = $file !== '' && is_file($file) ? filemtime($file) : false;
        if ($mtime === false) {
            return false;
        }
        $createdAt = $mtime;
    }

    return $createdAt < time() - $ttlByJob[$name];
};

$logProcessed = static function (array $job) use ($redactedJob): void {
    try {
        $encoded = json_encode($redactedJob($job), JSON_THROW_ON_ERROR);
    } catch (\Throwable) {
        $encoded = '{"job":"log_encoding_failed"}';
    }

    @file_put_contents(STORAGE_PATH . '/logs/queue.log', '[' . date(DATE_ATOM) . '] processed ' . $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
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

        // Explicit allowlist; unknown job names are rejected to prevent tampered queue files
        // from triggering unintended code paths.
        $knownJobs = ['SendEmailVerification', 'SendPasswordReset', 'AnswerPostedNotification', 'NotifyModerators', 'MentionNotification'];
        if (!in_array($name, $knownJobs, true)) {
            throw new RuntimeException("Unknown job name: {$name}");
        }

        if ($isStaleNotification($job)) {
            $queue->complete($job);
            $logProcessed($job + ['skipped' => 'stale_notification']);
            continue;
        }

        if ($name === 'SendEmailVerification') {
            $record = $users->findValidToken('email_verification', (string) $payload['token']);
            if ($record !== null && empty($record['email_verified_at'])) {
                $link = url('/email/verify/' . rawurlencode((string) $payload['token']));
                $mail->send((string) $record['email'], 'Verify your email', "Open this link to verify your email:\n\n{$link}");
                $notifications->create((int) $payload['user_id'], 'Email verification', ['message' => 'Verification email queued.', 'url' => '/settings/profile']);
            }
        }

        if ($name === 'SendPasswordReset') {
            $record = $users->findValidToken('password_reset', (string) $payload['token']);
            if ($record !== null) {
                $link = url('/reset-password/' . rawurlencode((string) $payload['token']));
                $mail->send((string) $record['email'], 'Reset your password', "Open this link to reset your password:\n\n{$link}");
            }
        }

        if ($name === 'AnswerPostedNotification') {
            $question = $database->fetch('SELECT user_id, slug, title FROM questions WHERE id = :id AND deleted_at IS NULL LIMIT 1', [':id' => (int) $payload['question_id']]);
            $answer = $database->fetch('SELECT id, question_id, user_id FROM answers WHERE id = :id AND deleted_at IS NULL LIMIT 1', [':id' => (int) ($payload['answer_id'] ?? 0)]);
            if ($question !== null && $answer !== null && (int) $answer['question_id'] === (int) $payload['question_id'] && (int) $answer['user_id'] !== (int) $question['user_id']) {
                $notifications->create((int) $question['user_id'], 'New answer', ['message' => 'A new answer was posted on ' . (string) $question['title'], 'url' => '/question/' . (string) $question['slug'] . '#answer-' . (int) $answer['id']]);
            }
        }

        if ($name === 'NotifyModerators') {
            foreach ($database->fetchAll('SELECT id FROM users WHERE role IN ("moderator", "administrator")') as $moderator) {
                $notifications->create((int) $moderator['id'], 'New question', ['message' => 'A new question was posted.', 'url' => '/moderator']);
            }
        }

        if ($name === 'MentionNotification') {
            $notifications->create(
                (int) $payload['user_id'],
                is_string($payload['type'] ?? null) && (string) $payload['type'] !== '' ? (string) $payload['type'] : 'Mention',
                [
                    'message' => is_string($payload['message'] ?? null) && (string) $payload['message'] !== '' ? (string) $payload['message'] : 'You were mentioned.',
                    'url' => safe_internal_path((string) ($payload['url'] ?? '')),
                ]
            );
        }

        $queue->complete($job);
        $logProcessed($job);
    } catch (\Throwable $e) {
        $queue->release($job, $e->getMessage());
        $logger->error($e);
    }
}
