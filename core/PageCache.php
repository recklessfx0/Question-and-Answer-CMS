<?php

declare(strict_types=1);

namespace Core;

final readonly class PageCache
{
    public function __construct(private string $path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0750, true);
        }
    }

    public function get(string $uri): ?string
    {
        $file = $this->file($uri);
        if (!is_file($file)) {
            return null;
        }

        $contents = (string) file_get_contents($file);
        $newline = strpos($contents, "\n");
        if ($newline === false || (int) substr($contents, 0, $newline) < time()) {
            if (is_file($file)) {
                unlink($file);
            }
            return null;
        }

        return substr($contents, $newline + 1);
    }

    public function put(string $uri, string $html, int $seconds): void
    {
        file_put_contents($this->file($uri), (string) (time() + $seconds) . "\n" . $html, LOCK_EX);
    }

    private function file(string $uri): string
    {
        return rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . hash('sha256', $uri) . '.html';
    }
}
