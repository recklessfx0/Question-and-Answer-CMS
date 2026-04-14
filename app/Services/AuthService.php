<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;
use Core\Queue;
use Core\Session;
use Core\Slugger;
use Core\Validator;

final readonly class AuthService
{
    public function __construct(
        private UserModel $users,
        private Session $session,
        private Queue $queue,
        private Slugger $slugger,
    ) {}

    /** @return array{ok: bool, errors: array<string, string>} */
    public function register(string $username, string $email, string $password): array
    {
        $username = $this->slugger->slug($username);
        $validator = (new Validator())
            ->required('username', $username)
            ->length('username', $username, 3, 40)
            ->required('email', $email)
            ->email('email', $email)
            ->required('password', $password)
            ->length('password', $password, 12, 128);

        if (!$validator->passes()) {
            return ['ok' => false, 'errors' => $validator->errors()];
        }

        if ($this->users->findByEmail($email) !== null || $this->users->findByUsername($username) !== null) {
            return ['ok' => false, 'errors' => ['email' => 'That email or username is already registered.']];
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);
        try {
            $id = $this->users->createWithBootstrapRole($username, $email, $hash);
        } catch (\Throwable $e) {
            if (is_duplicate_key_exception($e)) {
                return ['ok' => false, 'errors' => ['email' => 'That email or username is already registered.']];
            }

            throw $e;
        }
        $user = $this->users->findSessionStateById($id);
        if ($user !== null) {
            $this->loginSession($user);
            $verifyToken = $this->users->createToken($id, 'email_verification', 86400);
            $this->queue->push('SendEmailVerification', ['user_id' => $id, 'email' => $email, 'token' => $verifyToken]);
        }

        return ['ok' => true, 'errors' => []];
    }

    public function login(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$N2FYcWJDQ1BtMHR1TWJGRw$Eu9opMtyQKLwdSMA1cx8ejLDu0TRaLAmcUaVKA1TZHE');
            return false;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            usleep(random_int(100000, 250000));
            return false;
        }

        $argonOptions = ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1];
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_ARGON2ID, $argonOptions)) {
            $this->users->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_ARGON2ID, $argonOptions));
        }

        $this->users->updateLastLogin((int) $user['id']);
        $safeUser = $this->users->findSessionStateById((int) $user['id']);
        if ($safeUser !== null) {
            $this->loginSession($safeUser);
        }

        return true;
    }

    public function logout(): void
    {
        $this->session->destroy();
    }

    public function requestPasswordReset(string $email): void
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return;
        }

        $token = $this->users->createToken((int) $user['id'], 'password_reset', 3600);
        $this->queue->push('SendPasswordReset', ['user_id' => (int) $user['id'], 'email' => $email, 'token' => $token]);
    }

    public function resetPassword(string $token, string $password): bool
    {
        if (mb_strlen($password) < 12 || mb_strlen($password) > 128) {
            return false;
        }

        return $this->users->resetPasswordWithToken(
            $token,
            password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1])
        );
    }

    public function verifyEmail(string $token): bool
    {
        $userId = $this->users->verifyEmailWithToken($token);
        if ($userId === null) {
            return false;
        }

        $user = $this->users->findSessionStateById($userId);
        if ($user !== null && (int) $this->session->get('user_id', 0) === $userId) {
            $this->loginSession($user);
        }

        return true;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $user = $this->session->get('user');
        return is_array($user) ? $user : null;
    }

    /** @param array<string, mixed> $user */
    private function loginSession(array $user): void
    {
        $this->session->regenerate();
        $this->session->set('user_id', (int) $user['id']);
        if (isset($user['auth_fingerprint'])) {
            $this->session->set('_auth_fingerprint', (string) $user['auth_fingerprint']);
            unset($user['auth_fingerprint']);
        }
        $this->session->set('user', $user);
    }
}
