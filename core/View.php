<?php

declare(strict_types=1);

namespace Core;

use App\Models\SettingsModel;
use RuntimeException;
use Throwable;

final class View
{
    /** @var array<string, string>|null */
    private ?array $cachedSettings = null;

    public function __construct(
        private readonly string $path,
        private readonly Container $container,
    ) {}

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = null): string
    {
        $session = $this->container->get(Session::class);
        if ($this->cachedSettings === null) {
            try {
                $this->cachedSettings = $this->container->get(SettingsModel::class)->all();
            } catch (Throwable) {
                $this->cachedSettings = [];
            }
        }
        $settings = $this->cachedSettings;

        $common = [
            'session' => $session,
            'csrf' => $this->container->get(Csrf::class),
            'authUser' => $session->get('user'),
            'flash' => $session->consumeFlash(),
            'securitySettings' => $settings,
        ];
        $file = $this->safeTemplatePath($template);
        $content = $this->renderFile($file, array_merge($common, $data));

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($this->safeTemplatePath($layout), array_merge($common, $data, ['content' => $content]));
    }

    private function safeTemplatePath(string $template): string
    {
        $template = trim($template, '/');
        if (str_contains($template, '..') || str_contains($template, "\0")) {
            throw new RuntimeException('Invalid template path');
        }

        $file = $this->path . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$template}");
        }

        return $file;
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        include $file;

        return (string) ob_get_clean();
    }
}
