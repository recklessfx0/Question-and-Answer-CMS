<?php

declare(strict_types=1);

namespace Core;

final class Markdown
{
    public function toHtml(string $markdown): string
    {
        $escaped = h($markdown);
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped) ?? $escaped;
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
        $escaped = preg_replace_callback(
            '/!\[([^\]]*)\]\((\/public-assets\/images\/[a-zA-Z0-9\/._-]+)\)/',
            static function (array $matches): string {
                $alt = trim((string) $matches[1]) !== '' ? (string) $matches[1] : \media_alt_text((string) $matches[2]);
                $path = $matches[2];
                $image = \uploaded_image($path, '', $alt);

                return '<a class="media-link" href="' . $path . '" data-media-popup="1">' . $image . '</a>';
            },
            $escaped
        ) ?? $escaped;
        $escaped = preg_replace_callback(
            '/\[uploaded video\]\((\/public-assets\/images\/[a-zA-Z0-9\/._-]+\.(?:mp4|webm))\)/',
            static function (array $matches): string {
                $path = $matches[1];
                $type = str_ends_with(strtolower($path), '.webm') ? 'video/webm' : 'video/mp4';

                return '<a class="media-link media-video-link" href="' . $path . '" data-media-popup="1"><video controls preload="metadata" aria-label="Uploaded video"><source src="' . $path . '" type="' . $type . '"></video></a>';
            },
            $escaped
        ) ?? $escaped;
        $escaped = preg_replace_callback(
            '/\[embedded video\]\((https?:\/\/[^\s)]+)\)/',
            fn (array $matches): string => $this->renderExternalVideo((string) $matches[1]),
            $escaped
        ) ?? $escaped;
        $escaped = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2" rel="nofollow ugc noopener">$1</a>', $escaped) ?? $escaped;

        $blocks = array_map('trim', preg_split('/\R{2,}/', $escaped) ?: []);
        $html = [];
        $gallery = [];
        foreach ($blocks as $block) {
            if ($block === '') {
                continue;
            }

            if ($this->isMediaBlock($block)) {
                $gallery[] = $block;
                continue;
            }

            $this->flushGallery($html, $gallery);

            if (str_starts_with($block, '&gt; ')) {
                $quote = preg_replace('/(?:^|<br\s*\/?>)\s*&gt;\s?/i', '<br>', nl2br($block, false)) ?? $block;
                $quote = preg_replace('/^(?:<br\s*\/?>)+/i', '', $quote) ?? $quote;
                $html[] = '<blockquote>' . $quote . '</blockquote>';
                continue;
            }

            if (str_starts_with($block, '### ')) {
                $html[] = '<h3>' . mb_substr($block, 4) . '</h3>';
                continue;
            }

            if (str_starts_with($block, '## ')) {
                $html[] = '<h2>' . mb_substr($block, 3) . '</h2>';
                continue;
            }

            $html[] = '<p>' . nl2br($block, false) . '</p>';
        }
        $this->flushGallery($html, $gallery);

        return implode("\n", $html);
    }

    private function renderExternalVideo(string $url): string
    {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $embed = $this->externalVideoEmbedUrl($url);
        if ($embed === null) {
            return '<a href="' . h($url) . '" rel="nofollow ugc noopener">' . h($url) . '</a>';
        }

        return '<div class="video-embed"><iframe src="' . h($embed['src']) . '" title="' . h($embed['title']) . '" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>';
    }

    /** @return array{src: string, title: string}|null */
    private function externalVideoEmbedUrl(string $url): ?array
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            $id = (string) ($query['v'] ?? '');
            if ($id === '' && str_starts_with($path, '/shorts/')) {
                $id = trim(substr($path, 8), '/');
            }
            if ($this->isYoutubeId($id)) {
                return ['src' => 'https://www.youtube-nocookie.com/embed/' . $id, 'title' => 'YouTube video'];
            }
        }

        if ($host === 'youtu.be') {
            $id = trim($path, '/');
            if ($this->isYoutubeId($id)) {
                return ['src' => 'https://www.youtube-nocookie.com/embed/' . $id, 'title' => 'YouTube video'];
            }
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $segment): bool => $segment !== ''));
            $id = end($segments);
            if (is_string($id) && preg_match('/^\d{6,15}$/', $id) === 1) {
                return ['src' => 'https://player.vimeo.com/video/' . $id, 'title' => 'Vimeo video'];
            }
        }

        return null;
    }

    private function isYoutubeId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1;
    }

    private function isMediaBlock(string $block): bool
    {
        return preg_match('/^(?:<a class="media-link[^"]*" href="\/public-assets\/images\/[^"]+" data-media-popup="1">.*<\/a>|<div class="video-embed">.*<\/div>)$/s', $block) === 1;
    }

    /** @param array<int, string> $html @param array<int, string> $gallery */
    private function flushGallery(array &$html, array &$gallery): void
    {
        if ($gallery === []) {
            return;
        }

        if (count($gallery) === 1) {
            $html[] = $gallery[0];
        } else {
            $count = min(count($gallery), 6);
            $html[] = '<div class="post-media-gallery post-media-gallery-' . $count . '">' . implode('', $gallery) . '</div>';
        }
        $gallery = [];
    }
}
