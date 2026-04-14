<?php

declare(strict_types=1);

namespace Core;

final readonly class Cache
{
    public function __construct(private string $path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0750, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->file($key);
        if (!is_file($file)) {
            return $default;
        }

        $payload = json_decode((string) file_get_contents($file), true);
        if (!is_array($payload) || (($payload['expires_at'] ?? 0) < time())) {
            if (is_file($file)) {
                unlink($file);
            }
            return $default;
        }

        return $payload['value'] ?? $default;
    }

    public function set(string $key, mixed $value, int $seconds = 300): void
    {
        file_put_contents($this->file($key), json_encode([
            'expires_at' => time() + $seconds,
            'value' => $value,
        ], JSON_THROW_ON_ERROR), LOCK_EX);
    }

    public function remember(string $key, int $seconds, callable $callback): mixed
    {
        $cached = $this->get($key, null);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $seconds);
        return $value;
    }

    public function forget(string $key): void
    {
        $file = $this->file($key);
        if (is_file($file)) {
            unlink($file);
        }
    }

    private function file(string $key): string
    {
        return rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    }
}
