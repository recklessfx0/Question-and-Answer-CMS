<?php

declare(strict_types=1);

namespace Core;

final class RateLimiter
{
    public function __construct(private string $path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0750, true);
        }
    }

    /**
     * Atomically record a hit and return true if the limit is exceeded AFTER this hit.
     * Uses an exclusive file lock so concurrent requests cannot both slip through
     * the check+increment window (TOCTOU race).
     */
    public function hitAndCheck(string $bucket, string $identifier, int $limit, int $seconds): bool
    {
        $file = $this->file($bucket, $identifier);
        
        // Ensure directory exists before attempting to write
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            // If we can't create the directory, fail open
            return false;
        }
        
        $fh = fopen($file, 'c+');
        if ($fh === false) {
            // If we can't open the file, fail open (don't block the request)
            return false;
        }

        flock($fh, LOCK_EX);

        try {
            $size = filesize($file);
            $raw = $size > 0 ? fread($fh, (int) $size) : '';
            $hits = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            $threshold = time() - $seconds;
            $hits = array_values(array_filter(is_array($hits) ? $hits : [], static fn (mixed $hit): bool => is_int($hit) && $hit >= $threshold));
            $hits[] = time();

            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($hits, JSON_THROW_ON_ERROR));
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }

        return count($hits) > $limit;
    }

    /**
     * @deprecated Use hitAndCheck() for atomic check+increment. Kept for backwards compatibility.
     */
    public function tooManyAttempts(string $bucket, string $identifier, int $limit, int $seconds): bool
    {
        return count($this->hits($bucket, $identifier, $seconds)) >= $limit;
    }

    /**
     * @deprecated Use hitAndCheck() for atomic check+increment. Kept for backwards compatibility.
     */
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

    public function attemptCount(string $bucket, string $identifier, int $seconds): int
    {
        return count($this->hits($bucket, $identifier, $seconds));
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
