<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Request;
use Core\Response;
use Core\Session;

final class ModeratorMiddleware
{
    public function __construct(private Session $session) {}

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->session->has('user_id')) {
            $this->session->flash('error', 'Sign in to continue.');
            return Response::redirect('/login');
        }

        $role = active_role($this->session->get('user'));
        if (!in_array($role, ['moderator', 'administrator'], true)) {
            return new Response('Moderator access required.', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return $next($request);
    }
}
