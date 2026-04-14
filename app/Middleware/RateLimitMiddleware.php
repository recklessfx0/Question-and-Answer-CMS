<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\RateLimiter;
use Core\Request;
use Core\Response;

final readonly class RateLimitMiddleware
{
    public function __construct(private RateLimiter $limiter) {}

    public function handle(Request $request, callable $next): Response
    {
        $bucket = $request->method . ':' . $request->path;
        $identifier = $request->clientIp();
        [$limit, $window] = $this->limitsForPath($request->path);

        if ($this->limiter->tooManyAttempts($bucket, $identifier, $limit, $window)) {
            return new Response('Too many attempts. Try again later.', 429, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $this->limiter->hit($bucket, $identifier, $window);
        return $next($request);
    }

    /** @return array{0: int, 1: int} */
    private function limitsForPath(string $path): array
    {
        return match (true) {
            $path === '/login' => [5, 900],
            $path === '/register' => [5, 3600],
            $path === '/forgot-password' => [3, 3600],
            str_starts_with($path, '/reset-password/') => [5, 3600],
            default => [20, 60],
        };
    }
}
