<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SettingsModel;
use Core\Request;
use RuntimeException;

final readonly class RecaptchaService
{
    public function __construct(private SettingsModel $settings) {}

    public function enabled(): bool
    {
        return $this->isEnabled('recaptcha_enabled')
            && $this->settings->get('recaptcha_site_key') !== ''
            && $this->settings->get('recaptcha_secret_key') !== '';
    }

    public function verify(Request $request): bool
    {
        if (!$this->enabled()) {
            return true;
        }

        $token = $request->string('g-recaptcha-response', 4096);
        if ($token === '') {
            return false;
        }

        $payload = http_build_query([
            'secret' => $this->settings->get('recaptcha_secret_key'),
            'response' => $token,
            'remoteip' => $request->clientIp(),
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        try {
            $response = $this->withoutWarnings(static fn (): string|false => file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context));
        } catch (RuntimeException) {
            return false;
        }

        $data = is_string($response) ? json_decode($response, true) : null;

        return is_array($data) && ($data['success'] ?? false) === true;
    }

    private function isEnabled(string $key): bool
    {
        return in_array(strtolower($this->settings->get($key)), ['1', 'true', 'yes', 'on'], true);
    }

    private function withoutWarnings(callable $callback): mixed
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new RuntimeException($message, $severity);
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }
}
