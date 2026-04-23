<?php

declare(strict_types=1);

namespace Core;

use App\Models\UserModel;
use App\Middleware\SecurityHeadersMiddleware;
use App\Services\TranslationService;
use Throwable;

final class Kernel
{
    public function __construct(
        private Container $container,
        private Router $router,
    ) {}

    public function handle(Request $request): Response
    {
        try {
            $this->container->get(Session::class)->start();
            $this->syncAuthenticatedUser();
            $this->container->get(TranslationService::class)->boot($request);
            $localeRedirect = $this->normalizedLocaleRedirect($request);
            if ($localeRedirect !== null) {
                return $localeRedirect;
            }

            $cached = $this->pageCacheLookup($request);
            if ($cached !== null) {
                $response = (new Response($cached, 200, ['Content-Type' => 'text/html; charset=utf-8']))->withHeader('X-Page-Cache', 'HIT');
                $response->withHeader('Content-Language', current_language());
                $response->withHeader('Vary', 'Accept-Language');
                return $this->container->get(SecurityHeadersMiddleware::class)->handle($request, static fn (): Response => $response);
            }

            $match = $this->router->match($request);
            if ($match === null) {
                return $this->errorResponse(404, 'Page not found');
            }

            /** @var Route $route */
            $route = $match['route'];
            $params = $match['params'];
            $handler = fn (Request $r): Response => $route->dispatch($this->container, $r, $params);

            foreach (array_reverse(array_merge([SecurityHeadersMiddleware::class], $route->middleware)) as $middlewareClass) {
                $next = $handler;
                $handler = function (Request $r) use ($middlewareClass, $next): Response {
                    $middleware = $this->container->get($middlewareClass);
                    return $middleware->handle($r, $next);
                };
            }

            $response = $handler($request);
            if ($this->canPageCache($request, $response)) {
                $this->container->get(PageCache::class)->put($this->pageCacheKey($request), $response->body(), 120);
                $response->withHeader('X-Page-Cache', 'MISS');
            }
            $response->withHeader('Content-Language', current_language());
            $response->withHeader('Vary', 'Accept-Language');

            return $response;
        } catch (Throwable $e) {
            $this->container->get(Logger::class)->error($e);
            return $this->errorResponse(500, 'Something went wrong');
        }
    }

    private function pageCacheLookup(Request $request): ?string
    {
        return $this->canAttemptPageCache($request) ? $this->container->get(PageCache::class)->get($this->pageCacheKey($request)) : null;
    }

    private function pageCacheKey(Request $request): string
    {
        return current_language() . ':' . $request->uri;
    }

    private function syncAuthenticatedUser(): void
    {
        $session = $this->container->get(Session::class);
        $userId = (int) $session->get('user_id', 0);
        if ($userId < 1) {
            return;
        }

        $user = $this->container->get(UserModel::class)->findSessionStateById($userId);
        if ($user === null) {
            $session->destroy();
            return;
        }

        if (!empty($user['banned_at']) || !empty($user['deleted_at'])) {
            $session->destroy();
            return;
        }

        $currentFingerprint = (string) $session->get('_auth_fingerprint', '');
        $latestFingerprint = (string) ($user['auth_fingerprint'] ?? '');
        if ($currentFingerprint !== '' && $latestFingerprint !== '' && !hash_equals($currentFingerprint, $latestFingerprint)) {
            $session->destroy();
            return;
        }

        $session->set('_auth_fingerprint', $latestFingerprint);
        if (array_key_exists('auth_fingerprint', $user)) {
            unset($user['auth_fingerprint']);
        }
        $session->set('user', $user);
    }

    private function canAttemptPageCache(Request $request): bool
    {
        $session = $this->container->get(Session::class);
        if (!$request->isGet() || $session->has('user_id')) {
            return false;
        }

        foreach (['/login', '/register', '/forgot-password', '/reset-password', '/auth', '/admin', '/moderator', '/logout', '/question/', '/user/'] as $prefix) {
            if (str_starts_with($request->path, $prefix)) {
                return false;
            }
        }

        // Also exclude the bare /question path to avoid caching redirect-like responses
        if ($request->path === '/question') {
            return false;
        }

        return true;
    }

    private function canPageCache(Request $request, Response $response): bool
    {
        $contentType = $response->headers()['Content-Type'] ?? '';

        return $this->canAttemptPageCache($request) && $response->status() === 200 && str_contains($contentType, 'text/html');
    }

    private function normalizedLocaleRedirect(Request $request): ?Response
    {
        if (!$request->isGet()) {
            return null;
        }

        $parts = parse_url($request->uri);
        if (!is_array($parts)) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '/');
        $query = [];
        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str((string) $parts['query'], $query);
        }
        unset($query['lang']);

        $target = localized_path($request->path . ($query !== [] ? '?' . http_build_query($query) : ''));
        $current = $path . (isset($parts['query']) && $parts['query'] !== '' ? '?' . (string) $parts['query'] : '');
        if ($target === $current) {
            return null;
        }

        return Response::redirect($target, 301);
    }

    private function errorResponse(int $status, string $message): Response
    {
        $view = $this->container->get(View::class);

        return new Response(
            $view->render('errors/http', ['status' => $status, 'message' => $message], 'layouts/main'),
            $status,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }
}
