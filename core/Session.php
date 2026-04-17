<?php

declare(strict_types=1);

namespace Core;

final class Session
{
    public function __construct(
        private readonly string $path,
        private readonly bool $secureCookies,
    ) {}

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (!is_dir($this->path)) {
            mkdir($this->path, 0750, true);
        }

        session_save_path($this->path);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_name('qa_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->secureCookies,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $userAgent = $this->serverUserAgent();
        if (isset($_SESSION['_ua']) && $_SESSION['_ua'] !== $userAgent) {
            $this->destroy();
            session_start();
            // New session: initialise tracking fields immediately
            $_SESSION['_created_at'] = time();
            $_SESSION['_last_regeneration'] = time();
            $_SESSION['_ua'] = $userAgent;
            return;
        }

        $_SESSION['_created_at'] ??= time();
        $_SESSION['_last_regeneration'] ??= 0;
        $_SESSION['_ua'] ??= $userAgent;

        if (time() - (int) $_SESSION['_created_at'] > 43200) {
            $this->destroy();
            session_start();
            $_SESSION['_created_at'] = time();
            $_SESSION['_last_regeneration'] = 0;
            $_SESSION['_ua'] = $userAgent;
            return;
        }

        if (time() - (int) $_SESSION['_last_regeneration'] > 300) {
            $this->regenerate();
        }
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_last_regeneration'] = time();
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $this->secureCookies,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_destroy();
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    private function serverUserAgent(): string
    {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return '';
        }
        $ua = $_SERVER['HTTP_USER_AGENT'];
        return is_string($ua) ? substr($ua, 0, 500) : '';
    }

    public function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key][] = $message;
    }

    /** @return array<string, array<int, string>> */
    public function consumeFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return is_array($flash) ? $flash : [];
    }
}
