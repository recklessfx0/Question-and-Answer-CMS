<?php

declare(strict_types=1);

namespace Core;

use RuntimeException;

final class Router
{
    /** @var array<string, array<string, Route>> */
    private array $staticRoutes = [];

    /** @var array<string, array<int, Route[]>> */
    private array $dynamicRoutes = [];

    /** @param array<int, string> $middleware */
    public function get(string $path, array|string $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /** @param array<int, string> $middleware */
    public function post(string $path, array|string $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /** @param array<int, string> $middleware */
    public function delete(string $path, array|string $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    /** @param array<int, string> $middleware */
    public function add(string $method, string $path, array|string $handler, array $middleware = []): void
    {
        $route = new Route($method, self::normalize($path), $handler, $middleware);

        if (!$route->isDynamic()) {
            $this->staticRoutes[$method][$route->path] = $route;
            return;
        }

        $this->dynamicRoutes[$method][count($route->segments)][] = $route;
    }

    /** @return array{route: Route, params: array<string, string>}|null */
    public function match(Request $request): ?array
    {
        $method = $request->method;
        $path = self::normalize($request->path);

        if (isset($this->staticRoutes[$method][$path])) {
            return ['route' => $this->staticRoutes[$method][$path], 'params' => []];
        }

        $segments = Route::segments($path);
        foreach ($this->dynamicRoutes[$method][count($segments)] ?? [] as $route) {
            $params = [];
            foreach ($route->segments as $index => $segment) {
                $actual = $segments[$index] ?? '';
                if ($segment !== '' && $segment[0] === '{') {
                    $params[trim($segment, '{}')] = rawurldecode($actual);
                    continue;
                }

                if ($segment !== $actual) {
                    continue 2;
                }
            }

            return ['route' => $route, 'params' => $params];
        }

        return null;
    }

    private static function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}

final class Route
{
    /** @var array<int, string> */
    public array $segments;

    /** @param array<int, string> $middleware */
    public function __construct(
        public string $method,
        public string $path,
        public array|string $handler,
        public array $middleware,
    ) {
        $this->segments = self::segments($path);
    }

    public static function segments(string $path): array
    {
        return $path === '/' ? [] : explode('/', trim($path, '/'));
    }

    public function isDynamic(): bool
    {
        foreach ($this->segments as $segment) {
            if ($segment !== '' && $segment[0] === '{') {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, string> $params */
    public function dispatch(Container $container, Request $request, array $params): Response
    {
        if (is_string($this->handler)) {
            $handler = $this->handler;
            if (!function_exists($handler)) {
                throw new RuntimeException("Route handler not found: {$handler}");
            }

            $response = $handler($request, $params);
            return $response instanceof Response ? $response : new Response((string) $response);
        }

        [$class, $method] = $this->handler;
        $controller = $container->get($class);
        $reflection = new \ReflectionMethod($controller, $method);
        $response = match ($reflection->getNumberOfParameters()) {
            0 => $controller->{$method}(),
            1 => $controller->{$method}($request),
            default => $controller->{$method}($request, $params),
        };

        return $response instanceof Response ? $response : new Response((string) $response);
    }
}
