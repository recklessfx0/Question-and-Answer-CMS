<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Models\UserModel;
use Core\Request;
use Core\Response;
use Core\Session;

final class AuthMiddleware
{
    public function __construct(private Session $session, private UserModel $users) {}

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->activeSessionUser();
        if ($user === null) {
            return $this->redirectToLogin($request);
        }

        return $next($request);
    }

    /** @return array<string, mixed>|null */
    private function activeSessionUser(): ?array
    {
        $userId = (int) $this->session->get('user_id', 0);
        if ($userId < 1) {
            return null;
        }

        $user = $this->users->findSessionStateById($userId);
        if ($user === null || !empty($user['deleted_at']) || !empty($user['banned_at'])) {
            $this->session->destroy();
            return null;
        }

        if (array_key_exists('auth_fingerprint', $user)) {
            $this->session->set('_auth_fingerprint', (string) $user['auth_fingerprint']);
            unset($user['auth_fingerprint']);
        }
        $this->session->set('user', $user);

        return $user;
    }

    private function redirectToLogin(Request $request): Response
    {
        if (preg_match('#^/post/(?:question|answer|comment)/\d+/react$#', $request->path) === 1) {
            $this->session->flash('error', 'Create an account to react.');
            return Response::redirect('/register');
        }

        $this->session->flash('error', 'Sign in to continue.');
        return Response::redirect('/login');
    }
}
