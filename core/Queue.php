<?php

declare(strict_types=1);

namespace Core;

use RuntimeException;

final class Queue
{
    private string $pendingPath;
    private string $processingPath;

    public function __construct(private readonly string $path)
    {
        $root = rtrim($path, '/\\');
        $this->pendingPath = $root . DIRECTORY_SEPARATOR . 'pending';
        $this->processingPath = $root . DIRECTORY_SEPARATOR . 'processing';

        foreach ([$root, $this->pendingPath, $this->processingPath] as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0750, true);
            }
        }

        $this->migrateLegacyJobs($root);
    }

    /** @param array<string, mixed> $payload */
    public function push(string $job, array $payload): void
    {
        $file = $this->pendingPath . DIRECTORY_SEPARATOR . date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.json';
        $written = file_put_contents($file, json_encode([
            'job' => $job,
            'payload' => $payload,
            'attempts' => 0,
            'available_at' => time(),
            'created_at' => date(DATE_ATOM),
        ], JSON_THROW_ON_ERROR), LOCK_EX);
        if ($written === false) {
            throw new RuntimeException('Unable to enqueue job.');
        }
    }

    /** @return array<string, mixed>|null */
    public function pop(): ?array
    {
        $this->reclaimStaleJobs();

        $files = glob($this->pendingPath . DIRECTORY_SEPARATOR . '*.json') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $reserved = $this->processingPath . DIRECTORY_SEPARATOR . basename($file, '.json') . '-' . bin2hex(random_bytes(4)) . '.json';
            if (!@rename($file, $reserved)) {
                continue;
            }

            $payload = json_decode((string) file_get_contents($reserved), true);
            if (!is_array($payload)) {
                @unlink($reserved);
                continue;
            }

            if ((int) ($payload['available_at'] ?? 0) > time()) {
                @rename($reserved, $this->pendingPath . DIRECTORY_SEPARATOR . basename($reserved));
                continue;
            }

            $payload['attempts'] = (int) ($payload['attempts'] ?? 0);
            $payload['__queue_file'] = $reserved;

            return $payload;
        }

        return null;
    }

    /** @param array<string, mixed> $job */
    public function complete(array $job): void
    {
        $file = (string) ($job['__queue_file'] ?? '');
        if ($file !== '' && is_file($file)) {
            unlink($file);
        }
    }

    /** @param array<string, mixed> $job */
    public function release(array $job, ?string $error = null, ?int $delaySeconds = null): void
    {
        $file = (string) ($job['__queue_file'] ?? '');
        if ($file === '' || !is_file($file)) {
            return;
        }

        $payload = json_decode((string) file_get_contents($file), true);
        if (!is_array($payload)) {
            unlink($file);
            return;
        }

        $attempts = (int) ($payload['attempts'] ?? 0) + 1;
        $payload['attempts'] = $attempts;
        $payload['available_at'] = time() + ($delaySeconds ?? min(300, 5 * (2 ** min(6, $attempts - 1))));
        if ($error !== null && $error !== '') {
            $payload['last_error'] = mb_substr(str_replace("\0", '', $error), 0, 500);
        }

        file_put_contents($file, json_encode($payload, JSON_THROW_ON_ERROR), LOCK_EX);
        @rename($file, $this->pendingPath . DIRECTORY_SEPARATOR . basename($file));
    }

    private function reclaimStaleJobs(): void
    {
        $files = glob($this->processingPath . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $threshold = time() - 900;

        foreach ($files as $file) {
            $mtime = @filemtime($file);
            if ($mtime === false || $mtime > $threshold) {
                continue;
            }

            $this->release(['__queue_file' => $file], 'Recovered abandoned queue reservation.');
        }
    }

    private function migrateLegacyJobs(string $root): void
    {
        $files = glob($root . DIRECTORY_SEPARATOR . '*.json') ?: [];
        foreach ($files as $file) {
            @rename($file, $this->pendingPath . DIRECTORY_SEPARATOR . basename($file));
        }
    }
}
