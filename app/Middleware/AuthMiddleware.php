<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Request;
use Core\Response;
use Core\Session;

final readonly class AuthMiddleware
{
    public function __construct(private Session $session) {}

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->session->has('user_id')) {
            if (preg_match('#^/post/(?:question|answer|comment)/\d+/react$#', $request->path) === 1) {
                $this->session->flash('error', 'Create an account to react.');
                return Response::redirect('/register');
            }

            $this->session->flash('error', 'Sign in to continue.');
            return Response::redirect('/login');
        }

        return $next($request);
    }
}
