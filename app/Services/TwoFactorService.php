<?php

declare(strict_types=1);

namespace App\Services;

final readonly class TwoFactorService
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const int STEP_SECONDS = 30;
    private const int CODE_DIGITS = 6;

    public function generateSecret(int $length = 32): string
    {
        $bytes = random_bytes(max(10, (int) ceil($length * 5 / 8)));
        $secret = $this->base32Encode($bytes);

        return substr($secret, 0, $length);
    }

    public function verify(string $secret, string $code, ?int $time = null, int $window = 1): bool
    {
        $secret = $this->normalizeSecret($secret);
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if ($secret === '' || strlen($code) !== self::CODE_DIGITS) {
            return false;
        }

        $counter = intdiv($time ?? time(), self::STEP_SECONDS);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->codeForCounter($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public function provisioningUri(string $issuer, string $account, string $secret): string
    {
        $issuer = trim($issuer) !== '' ? trim($issuer) : 'Question And Answer Platform';
        $account = trim($account) !== '' ? trim($account) : 'admin';
        $label = rawurlencode($issuer . ':' . $account);

        return 'otpauth://totp/' . $label
            . '?secret=' . rawurlencode($this->normalizeSecret($secret))
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::CODE_DIGITS
            . '&period=' . self::STEP_SECONDS;
    }

    private function codeForCounter(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        if ($key === '') {
            return '';
        }

        $binaryCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** self::CODE_DIGITS)), self::CODE_DIGITS, '0', STR_PAD_LEFT);
    }

    private function normalizeSecret(string $secret): string
    {
        return preg_replace('/[^A-Z2-7]+/', '', strtoupper($secret)) ?? '';
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $output .= self::ALPHABET[bindec($chunk)];
        }

        return $output;
    }

    private function base32Decode(string $secret): string
    {
        $bits = '';
        foreach (str_split($this->normalizeSecret($secret)) as $char) {
            $position = strpos(self::ALPHABET, $char);
            if ($position === false) {
                return '';
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }

        return $output;
    }
}
