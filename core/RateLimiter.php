<?php

declare(strict_types=1);

namespace Core;

final readonly class RateLimiter
{
    public function __construct(private string $path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0750, true);
        }
    }

    public function tooManyAttempts(string $bucket, string $identifier, int $limit, int $seconds): bool
    {
        return count($this->hits($bucket, $identifier, $seconds)) >= $limit;
    }

    public function hit(string $bucket, string $identifier, int $seconds): void
    {
        $hits = $this->hits($bucket, $identifier, $seconds);
        $hits[] = time();
        file_put_contents($this->file($bucket, $identifier), json_encode($hits, JSON_THROW_ON_ERROR), LOCK_EX);
    }

    public function reset(string $bucket, string $identifier): void
    {
        $file = $this->file($bucket, $identifier);
        if (is_file($file)) {
            unlink($file);
        }
    }

    /** @return array<int, int> */
    private function hits(string $bucket, string $identifier, int $seconds): array
    {
        $file = $this->file($bucket, $identifier);
        $hits = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        $threshold = time() - $seconds;

        return array_values(array_filter(is_array($hits) ? $hits : [], static fn (mixed $hit): bool => is_int($hit) && $hit >= $threshold));
    }

    private function file(string $bucket, string $identifier): string
    {
        return rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . hash('sha256', $bucket . '|' . $identifier) . '.json';
    }
}
