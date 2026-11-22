<?php

declare(strict_types=1);

namespace Core;

final class PageCache
{
    public function __construct(private string $path)
    {
        if (!is_dir($path)) {
            @mkdir($path, 0750, true);
        }
    }

    public function get(string $uri, string $nonce): ?string
    {
        $file = $this->file($uri);
        if (!is_file($file)) {
            return null;
        }

        $contents = @file_get_contents($file);
        if ($contents === false) {
            return null;
        }

        $parts = explode("\n", $contents, 3);
        if (count($parts) !== 3 || (int) $parts[0] <= time() || !preg_match('/^[a-f0-9]{32}$/D', $parts[1])) {
            return null;
        }

        return str_replace('nonce="' . $parts[1] . '"', 'nonce="' . $nonce . '"', $parts[2]);
    }

    public function put(string $uri, string $html, string $nonce, int $seconds): void
    {
        $file = $this->file($uri);
        $temporary = $file . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $contents = (time() + $seconds) . "\n" . $nonce . "\n" . $html;
        if (@file_put_contents($temporary, $contents) !== strlen($contents) || !@rename($temporary, $file)) {
            @unlink($temporary);
        }
    }

    private function file(string $uri): string
    {
        return rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . hash('sha256', 'v8:' . $uri) . '.html';
    }
}
