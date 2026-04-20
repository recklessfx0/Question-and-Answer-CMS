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
            $capabilities = $this->ehlo($socket, $serverName);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!$this->enableTls($socket)) {
                    throw new RuntimeException('SMTP STARTTLS negotiation failed.');
                }
                $capabilities = $this->ehlo($socket, $serverName);
            }

            if ($username !== '') {
                $this->authenticate($socket, $capabilities, $username, $password);
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

    /** @param array<int, string> $capabilities */
    private function authenticate(mixed $socket, array $capabilities, string $username, string $password): void
    {
        $authLine = '';
        foreach ($capabilities as $capability) {
            $normalized = strtoupper($capability);
            if (str_starts_with($normalized, 'AUTH ') || str_starts_with($normalized, 'AUTH=')) {
                $authLine .= ' ' . strtoupper($capability);
            }
        }

        if (str_contains($authLine, ' PLAIN')) {
            $this->command($socket, 'AUTH PLAIN ' . base64_encode("\0" . $username . "\0" . $password), [235]);
            return;
        }

        if ($authLine === '' || str_contains($authLine, ' LOGIN')) {
            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($username), [334]);
            $this->command($socket, base64_encode($password), [235]);
            return;
        }

        throw new RuntimeException('SMTP server does not support AUTH PLAIN or AUTH LOGIN.');
    }

    private function enableTls(mixed $socket): bool
    {
        foreach ($this->tlsCryptoMethods() as $method) {
            if (@stream_socket_enable_crypto($socket, true, $method)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, int> */
    private function tlsCryptoMethods(): array
    {
        $methods = [];
        foreach (['STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT', 'STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT', 'STREAM_CRYPTO_METHOD_TLS_CLIENT'] as $constant) {
            if (defined($constant)) {
                $methods[] = (int) constant($constant);
            }
        }

        return array_values(array_unique($methods));
    }

    /** @param array<int, int> $expected */
    private function command(mixed $socket, string $command, array $expected): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $expected);
    }

    /** @return array<int, string> */
    private function ehlo(mixed $socket, string $serverName): array
    {
        fwrite($socket, 'EHLO ' . $serverName . "\r\n");
        return $this->expect($socket, [250]);
    }

    /** @param array<int, int> $expected @return array<int, string> */
    private function expect(mixed $socket, array $expected): array
    {
        $line = '';
        $lines = [];
        do {
            $line = fgets($socket);
            if (!is_string($line)) {
                throw new RuntimeException('SMTP server closed the connection.');
            }
            $lines[] = rtrim($line, "\r\n");
            $code = (int) substr($line, 0, 3);
        } while (isset($line[3]) && $line[3] === '-');

        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('Unexpected SMTP response: ' . trim($line));
        }

        return array_values(array_map(
            static fn (string $responseLine): string => trim(substr($responseLine, 4)),
            $lines
        ));
    }
}
