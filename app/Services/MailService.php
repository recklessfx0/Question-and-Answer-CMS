<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SettingsModel;
use RuntimeException;

final class MailService
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private SettingsModel $settings,
        private array $config,
    ) {}

    public function send(string $to, string $subject, string $body): void
    {
        $settings = $this->settings->all();
        $to = str_replace(["\r", "\n", "\0"], '', trim($to));
        $subject = str_replace(["\r", "\n", "\0"], '', trim($subject));
        if ($to === '' || $subject === '') {
            throw new RuntimeException('Mail recipient and subject are required.');
        }

        if (setting_enabled($settings, 'smtp_enabled')) {
            $this->sendSmtp($settings, $to, $subject, $body);
            return;
        }

        $headers = [
            'From: ' . $this->fromHeader($settings),
            'Content-Type: text/plain; charset=UTF-8',
        ];
        if (!mail($to, $subject, $body, implode("\r\n", $headers))) {
            throw new RuntimeException('Mail transport rejected the message.');
        }
    }

    /** @param array<string, string> $settings */
    private function sendSmtp(array $settings, string $to, string $subject, string $body): void
    {
        $host = trim((string) ($settings['smtp_host'] ?? ''));
        $port = (int) ($settings['smtp_port'] ?? 587);
        $encryption = strtolower(trim((string) ($settings['smtp_encryption'] ?? 'tls')));
        $username = trim((string) ($settings['smtp_username'] ?? ''));
        $password = (string) ($settings['smtp_password'] ?? '');

        if ($host === '' || $port < 1 || $port > 65535) {
            throw new RuntimeException('SMTP host and port are required.');
        }

        $transport = $encryption === 'ssl' ? 'ssl://' : '';
        $socket = @stream_socket_client($transport . $host . ':' . $port, $errorCode, $errorMessage, 15, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            throw new RuntimeException('SMTP connection failed: ' . $errorMessage . ' (' . $errorCode . ')');
        }

        try {
            stream_set_timeout($socket, 15);
            $this->expect($socket, [220]);
            $serverName = (string) (parse_url(site_url(), PHP_URL_HOST) ?: 'localhost');
            $this->command($socket, 'EHLO ' . $serverName, [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, $this->tlsCryptoMethod())) {
                    throw new RuntimeException('SMTP STARTTLS negotiation failed.');
                }
                $this->command($socket, 'EHLO ' . $serverName, [250]);
            }

            if ($username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($username), [334]);
                $this->command($socket, base64_encode($password), [235]);
            }

            $fromEmail = $this->fromEmail($settings);
            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);
            fwrite($socket, $this->message($settings, $fromEmail, $to, $subject, $body) . "\r\n.\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /** @param array<string, string> $settings */
    private function message(array $settings, string $fromEmail, string $to, string $subject, string $body): string
    {
        $headers = [
            'From: ' . $this->fromHeader($settings),
            'To: ' . $to,
            'Subject: ' . $subject,
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . (parse_url(site_url(), PHP_URL_HOST) ?: 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'Return-Path: <' . $fromEmail . '>',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . $this->smtpBody($body);
    }

    /** @param array<string, string> $settings */
    private function fromHeader(array $settings): string
    {
        $name = str_replace(['"', "\r", "\n", "\0"], '', trim((string) ($settings['smtp_from_name'] ?? '')));
        $email = $this->fromEmail($settings);

        return ($name !== '' ? '"' . $name . '" ' : '') . '<' . $email . '>';
    }

    /** @param array<string, string> $settings */
    private function fromEmail(array $settings): string
    {
        $configured = trim((string) ($settings['smtp_from_email'] ?? ''));
        if (filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        $host = (string) (parse_url(site_url(), PHP_URL_HOST) ?: parse_url((string) ($this->config['url'] ?? ''), PHP_URL_HOST) ?: 'localhost');
        return 'no-reply@' . $host;
    }

    private function smtpBody(string $body): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = explode("\n", $normalized);
        foreach ($lines as &$line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
        }
        unset($line);

        return implode("\r\n", $lines);
    }

    private function tlsCryptoMethod(): int
    {
        $method = 0;
        foreach (['STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT', 'STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT'] as $constant) {
            if (defined($constant)) {
                $method |= (int) constant($constant);
            }
        }

        return $method > 0 ? $method : STREAM_CRYPTO_METHOD_TLS_CLIENT;
    }

    /** @param array<int, int> $expected */
    private function command(mixed $socket, string $command, array $expected): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $expected);
    }

    /** @param array<int, int> $expected */
    private function expect(mixed $socket, array $expected): void
    {
        $line = '';
        do {
            $line = fgets($socket);
            if (!is_string($line)) {
                throw new RuntimeException('SMTP server closed the connection.');
            }
            $code = (int) substr($line, 0, 3);
        } while (isset($line[3]) && $line[3] === '-');

        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('Unexpected SMTP response: ' . trim($line));
        }
    }
}
