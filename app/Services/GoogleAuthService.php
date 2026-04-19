<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SettingsModel;
use App\Models\UserModel;
use Core\Session;
use Core\Slugger;
use RuntimeException;

final readonly class GoogleAuthService
{
    public function __construct(
        private SettingsModel $settings,
        private UserModel $users,
        private Session $session,
        private Slugger $slugger,
    ) {}

    public function enabled(): bool
    {
        return $this->isEnabled('google_auth_enabled')
            && $this->settings->get('google_client_id') !== ''
            && $this->settings->get('google_client_secret') !== '';
    }

    public function authUrl(): ?string
    {
        if (!$this->enabled()) {
            return null;
        }

        $state = bin2hex(random_bytes(32));
        $states = $this->oauthStates();
        $states[] = ['value' => $state, 'expires_at' => time() + 600];
        $this->session->set('_google_oauth_states', array_slice($states, -10));

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $this->settings->get('google_client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);
    }

    public function handleCallback(string $code, string $state): bool
    {
        if (!$this->enabled() || $code === '' || $state === '') {
            return false;
        }

        if (!$this->consumeOauthState($state)) {
            return false;
        }

        $token = $this->token($code);
        if ($token === null || !isset($token['access_token'])) {
            return false;
        }

        $profile = $this->profile((string) $token['access_token']);
        if ($profile === null || ($profile['email_verified'] ?? false) !== true || empty($profile['email'])) {
            return false;
        }

        $email = mb_strtolower((string) $profile['email']);
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            $name = trim((string) ($profile['name'] ?? ''));
            if ($name === '') {
                $name = strstr($email, '@', true) ?: 'member';
            }

            $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);
            $id = $this->createGoogleUser($name, $email, $hash);
            if ($id === null) {
                return false;
            }
            $this->users->markEmailVerified($id);
            $user = $this->users->findSessionStateById($id);
        } else {
            if (!empty($user['deleted_at']) || !empty($user['banned_at'])) {
                return false;
            }
            $this->users->markEmailVerified((int) $user['id']);
            $user = $this->users->findSessionStateById((int) $user['id']);
        }

        if ($user === null || !empty($user['deleted_at']) || !empty($user['banned_at'])) {
            return false;
        }

        $this->users->updateLastLogin((int) $user['id']);
        $this->session->regenerate();
        $this->session->set('user_id', (int) $user['id']);
        if (array_key_exists('auth_fingerprint', $user)) {
            $this->session->set('_auth_fingerprint', (string) $user['auth_fingerprint']);
            unset($user['auth_fingerprint']);
        }
        $this->session->set('user', $user);

        return true;
    }

    private function redirectUri(): string
    {
        return url('/auth/google/callback');
    }

    private function createGoogleUser(string $name, string $email, string $hash): ?int
    {
        $base = $this->slugger->slug($name);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $username = $this->users->uniqueUsername($base);

            try {
                return $this->users->createWithBootstrapRole($username, $email, $hash);
            } catch (\Throwable $e) {
                if (!is_duplicate_key_exception($e)) {
                    throw $e;
                }

                $existing = $this->users->findByEmail($email);
                if ($existing !== null) {
                    return (int) $existing['id'];
                }
            }
        }

        return null;
    }

    /** @return array<int, array{value: string, expires_at: int}> */
    private function oauthStates(): array
    {
        $states = $this->session->get('_google_oauth_states', []);
        if (!is_array($states)) {
            return [];
        }

        $valid = [];
        foreach ($states as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $value = (string) ($entry['value'] ?? '');
            $expiresAt = (int) ($entry['expires_at'] ?? 0);
            if ($value === '' || $expiresAt < time()) {
                continue;
            }

            $valid[] = ['value' => $value, 'expires_at' => $expiresAt];
        }

        return $valid;
    }

    private function consumeOauthState(string $state): bool
    {
        $states = $this->oauthStates();
        if ($states === []) {
            $this->session->remove('_google_oauth_states');
            return false;
        }

        $valid = false;
        $remaining = [];
        foreach ($states as $entry) {
            if (!$valid && hash_equals((string) $entry['value'], $state)) {
                $valid = true;
                continue;
            }

            $remaining[] = $entry;
        }

        if ($remaining === []) {
            $this->session->remove('_google_oauth_states');
        } else {
            $this->session->set('_google_oauth_states', $remaining);
        }

        return $valid;
    }

    /** @return array<string, mixed>|null */
    private function token(string $code): ?array
    {
        $payload = http_build_query([
            'code' => $code,
            'client_id' => $this->settings->get('google_client_id'),
            'client_secret' => $this->settings->get('google_client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        try {
            $response = $this->withoutWarnings(static fn (): string|false => file_get_contents('https://oauth2.googleapis.com/token', false, $context));
        } catch (RuntimeException) {
            return null;
        }

        $data = is_string($response) ? json_decode($response, true) : null;
        return is_array($data) ? $data : null;
    }

    /** @return array<string, mixed>|null */
    private function profile(string $accessToken): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $accessToken . "\r\nAccept: application/json\r\n",
                'timeout' => 5,
            ],
        ]);

        try {
            $response = $this->withoutWarnings(static fn (): string|false => file_get_contents('https://openidconnect.googleapis.com/v1/userinfo', false, $context));
        } catch (RuntimeException) {
            return null;
        }

        $data = is_string($response) ? json_decode($response, true) : null;
        return is_array($data) ? $data : null;
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
