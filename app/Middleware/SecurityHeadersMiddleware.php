<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Models\SettingsModel;
use Core\Container;
use Core\Request;
use Core\Response;
use Throwable;

final class SecurityHeadersMiddleware
{
    public function __construct(private Container $container) {}

    public function handle(Request $request, callable $next): Response
    {
        $GLOBALS['csp_nonce'] = bin2hex(random_bytes(16));
        $response = $next($request);
        $settings = [];
        try {
            $settings = $this->container->get(SettingsModel::class)->all();
        } catch (Throwable) {
            $settings = [];
        }

        $response->withHeader('Content-Security-Policy', $this->csp($settings, (string) $GLOBALS['csp_nonce']));
        $response->withHeader('X-Frame-Options', 'DENY');
        $response->withHeader('X-Content-Type-Options', 'nosniff');
        $response->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

        return $response;
    }

    /** @param array<string, string> $settings */
    private function csp(array $settings, string $nonce): string
    {
        $recaptcha = in_array(strtolower((string) ($settings['recaptcha_enabled'] ?? '')), ['1', 'true', 'yes', 'on'], true)
            && trim((string) ($settings['recaptcha_site_key'] ?? '')) !== '';

        $script = ["'self'", "'nonce-" . $nonce . "'"];
        $connect = ["'self'"];
        $frame = ['https://www.youtube-nocookie.com', 'https://player.vimeo.com'];
        if ($recaptcha) {
            $script[] = 'https://www.google.com';
            $script[] = 'https://www.gstatic.com';
            $connect[] = 'https://www.google.com';
            $frame[] = 'https://www.google.com';
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src ' . implode(' ', $script),
            "style-src 'self'",
            "img-src 'self' data: https:",
            "font-src 'self'",
            "object-src 'none'",
            'connect-src ' . implode(' ', $connect),
            'frame-src ' . implode(' ', $frame),
            "base-uri 'self'",
            "form-action 'self' https://accounts.google.com",
            "frame-ancestors 'none'",
            'upgrade-insecure-requests',
        ]);
    }
}
