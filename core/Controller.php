<?php

declare(strict_types=1);

namespace Core;

abstract class Controller
{
    public function __construct(
        protected readonly View $view,
        protected readonly Session $session,
        protected readonly Csrf $csrf,
    ) {}

    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return new Response(
            $this->view->render($template, $data, 'layouts/main'),
            $status,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect($path);
    }

    protected function flash(string $key, string $message): void
    {
        $this->session->flash($key, $message);
    }
}
