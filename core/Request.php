<?php

declare(strict_types=1);

namespace Core;

final class Request
{
    /** @param array<string, mixed> $get @param array<string, mixed> $post @param array<string, mixed> $files @param array<string, mixed> $server */
    private function __construct(
        public string $method,
        public string $uri,
        public string $path,
        public string $locale,
        public array $get,
        public array $post,
        public array $files,
        public array $server,
    ) {}

    public static function capture(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? '/' . trim($path, '/') : '/';
        [$locale, $path] = self::splitLocalePath($path);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if ($method === 'POST' && is_string($_POST['_method'] ?? null)) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        return new self($method, $uri, $path === '//' ? '/' : $path, $locale, $_GET, $_POST, $_FILES, $_SERVER);
    }

    /** @return array{string, string} */
    public static function splitLocalePath(string $path): array
    {
        if (preg_match('#^/([a-z]{2,3}(?:-[a-z0-9]{2,8})?)(?=/|$)#i', $path, $matches) !== 1) {
            return ['', $path];
        }

        $prefix = strtolower($matches[1]);
        if (in_array($prefix, ['ask', 'tag'], true)) {
            return ['', $path];
        }

        return [$prefix, substr($path, strlen($matches[0])) ?: '/'];
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function isPostLike(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    public function string(string $key, int $max = 2000, string $default = ''): string
    {
        $value = $this->input($key, $default);
        if (!is_scalar($value)) {
            return $default;
        }

        $clean = str_replace("\0", '', trim((string) $value));
        // Strip ASCII control characters
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean) ?? '';
        // Strip Unicode bidi override / embedding characters (Trojan Source / UI-spoofing)
        $clean = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $clean) ?? '';

        return mb_substr($clean, 0, $max);
    }

    public function int(string $key, int $default = 0, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
    {
        $value = filter_var($this->input($key), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max],
        ]);

        return $value === false ? $default : (int) $value;
    }

    public function clientIp(): string
    {
        $ip = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($ip, FILTER_VALIDATE_IP) === false ? '0.0.0.0' : $ip;
    }
}
