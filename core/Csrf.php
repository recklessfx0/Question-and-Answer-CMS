<?php

declare(strict_types=1);

namespace Core;

final class Csrf
{
    public function __construct(private Session $session) {}

    public function token(string $action = 'default'): string
    {
        $tokens = $this->session->get('_csrf', []);
        $token = bin2hex(random_bytes(32));
        $tokens[$action] ??= [];
        $tokens[$action][] = ['value' => $token, 'expires_at' => time() + 3600];
        $tokens[$action] = array_slice($tokens[$action], -20);
        $this->session->set('_csrf', $tokens);

        return $token;
    }

    public function validate(?string $token, string $action = 'default'): bool
    {
        $tokens = $this->session->get('_csrf', []);
        $entries = is_array($tokens) && isset($tokens[$action]) && is_array($tokens[$action]) ? $tokens[$action] : [];
        if ($entries === []) {
            return false;
        }

        $valid = false;
        $remaining = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || (int) ($entry['expires_at'] ?? 0) < time()) {
                continue;
            }

            if (!$valid && is_string($token) && hash_equals((string) ($entry['value'] ?? ''), $token)) {
                $valid = true;
                continue;
            }

            $remaining[] = $entry;
        }

        $tokens[$action] = $remaining;
        $this->session->set('_csrf', $tokens);

        return $valid;
    }

    public function field(string $action = 'default'): string
    {
        return '<input type="hidden" name="_csrf" value="' . h($this->token($action)) . '">';
    }
}
