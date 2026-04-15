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
        $bucket = $request->method . ':' . $this->normaliseBucket($request->path);
        $identifier = $request->clientIp();
        [$limit, $window] = $this->limitsForPath($request->path);

        // hitAndCheck() records the attempt and checks the limit atomically under a file lock,
        // preventing the TOCTOU race where two concurrent requests both pass the check.
        if ($this->limiter->hitAndCheck($bucket, $identifier, $limit, $window)) {
            return new Response('Too many attempts. Try again later.', 429, ['Content-Type' => 'text/plain; charset=utf-8', 'Retry-After' => (string) $window]);
        }

        return $next($request);
    }

    private function normaliseBucket(string $path): string
    {
        // Collapse dynamic path segments (UUIDs, slugs, numeric IDs) so that
        // /question/foo/answer and /question/bar/answer share the same bucket.
        return (string) preg_replace('/[a-z0-9]{8,}(-[a-z0-9]+)*|[0-9]+/i', '*', $path);
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
