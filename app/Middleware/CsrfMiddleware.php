<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Csrf;
use Core\Request;
use Core\Response;

final class CsrfMiddleware
{
    public function __construct(private Csrf $csrf) {}

    public function handle(Request $request, callable $next): Response
    {
        if ($request->isPostLike() && !$this->csrf->validate((string) $request->input('_csrf', ''), $request->path)) {
            return new Response('CSRF token validation failed.', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return $next($request);
    }
}
