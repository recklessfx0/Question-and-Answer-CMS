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
        return $this->container->get(SecurityHeadersMiddleware::class)->handle(
            $request,
            fn (Request $request): Response => $this->dispatch($request),
        );
    }

    private function dispatch(Request $request): Response
    {
        try {
            $session = $this->container->get(Session::class);
            if (isset($_COOKIE[Session::COOKIE_NAME]) || session_status() === PHP_SESSION_ACTIVE) {
                $session->start();
                $this->syncAuthenticatedUser();
            }
            $this->container->get(TranslationService::class)->boot($request);
            $localeRedirect = $this->normalizedLocaleRedirect($request);
            if ($localeRedirect !== null) {
                return $localeRedirect;
            }

            $cacheable = $this->canAttemptPageCache($request);
            $cached = $cacheable
                ? $this->container->get(PageCache::class)->get($this->pageCacheKey($request), csp_nonce())
                : null;
            if ($cached !== null) {
                $response = (new Response($cached, 200, ['Content-Type' => 'text/html; charset=utf-8']))->withHeader('X-Page-Cache', 'HIT');
                $response->withHeader('Content-Language', current_language());
                $response->withHeader('Vary', 'Accept-Language');
                return $response;
            }

            if (!$cacheable) {
                $session->start();
            }
            $match = $this->router->match($request);
            if ($match === null) {
                return $this->errorResponse(404, 'Page not found');
            }

            /** @var Route $route */
            $route = $match['route'];
            $params = $match['params'];
            $handler = fn (Request $r): Response => $route->dispatch($this->container, $r, $params);

            foreach (array_reverse($route->middleware) as $middlewareClass) {
                $next = $handler;
                $handler = function (Request $r) use ($middlewareClass, $next): Response {
                    $middleware = $this->container->get($middlewareClass);
                    return $middleware->handle($r, $next);
                };
            }

            $response = $handler($request);
            $contentType = $response->headers()['Content-Type'] ?? '';
            if ($cacheable && $response->status() === 200 && str_contains($contentType, 'text/html')) {
                $this->container->get(PageCache::class)->put($this->pageCacheKey($request), $response->body(), csp_nonce(), 120);
                $response->withHeader('X-Page-Cache', 'MISS');
            }
            if (!$cacheable) {
                $response->withHeader('Cache-Control', 'private, no-store');
            }
            $response->withHeader('Content-Language', current_language());
            $response->withHeader('Vary', 'Accept-Language');

            return $response;
        } catch (Throwable $e) {
            $this->container->get(Logger::class)->error($e);
            return new Response('Something went wrong', 500, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-store',
            ]);
        }
    }

    private function pageCacheKey(Request $request): string
    {
        return current_language() . ':' . $request->path;
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
        return $request->isGet()
            && !str_contains($request->uri, '?')
            && !$session->has('user_id')
            && !$session->has('user')
            && !$session->has('_flash')
            && in_array($request->path, [
                '/', '/activity', '/questions', '/unanswered', '/tags', '/users',
                '/about', '/privacy', '/terms', '/community-guidelines',
            ], true);
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
