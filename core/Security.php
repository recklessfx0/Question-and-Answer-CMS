<?php
class Security {
    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        header("X-Frame-Options: SAMEORIGIN");
        header("X-Content-Type-Options: nosniff");
        header("X-XSS-Protection: 1; mode=block");
        header("Content-Security-Policy: default-src 'self'");
    }

    public static function csrf(): string {
        return $_SESSION['csrf'] ?? '';
    }

    public static function verifyCsrf(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf'] ?? '';
            if (!hash_equals($_SESSION['csrf'], $token)) {
                http_response_code(403);
                exit('Invalid CSRF token');
            }
        }
    }

    public static function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
