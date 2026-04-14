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
            $this->session->flash('error', 'Sign in to continue.');
            return Response::redirect('/login');
        }

        return $next($request);
    }
}
