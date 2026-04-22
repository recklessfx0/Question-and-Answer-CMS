<?php

declare(strict_types=1);

namespace Core;

use App\Models\UserModel;

final class Markdown
{
    /** @var array<string, string|null> */
    private array $mentionSlugs = [];

    public function __construct(private readonly ?UserModel $users = null) {}

    public function toHtml(string $markdown): string
    {
        $escaped = h($markdown);
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped) ?? $escaped;
        $codeBlocks = [];
        $htmlBlocks = [];
        $escaped = preg_replace_callback(
            '/`([^`]+)`/',
            static function (array $matches) use (&$codeBlocks): string {
                $placeholder = '%%CODE_BLOCK_' . count($codeBlocks) . '%%';
                $codeBlocks[$placeholder] = '<code>' . $matches[1] . '</code>';

                return $placeholder;
            },
            $escaped
        ) ?? $escaped;
        $escaped = preg_replace_callback(
            '/!\[([^\]]*)\]\((\/public-assets\/images\/[a-zA-Z0-9\/._-]+)\)/',
            static function (array $matches) use (&$htmlBlocks): string {
                $alt = trim((string) $matches[1]) !== '' ? (string) $matches[1] : \media_alt_text((string) $matches[2]);
                $path = $matches[2];
                $image = \uploaded_image($path, '', $alt);
                $placeholder = '%%HTML_BLOCK_' . count($htmlBlocks) . '%%';
                $htmlBlocks[$placeholder] = '<a class="media-link" href="' . $path . '" data-media-popup="1">' . $image . '</a>';

                return $placeholder;
            },
            $escaped
        ) ?? $escaped;
        $escaped = preg_replace_callback(
            '/\[uploaded video\]\((\/public-assets\/(?:images|videos)\/[a-zA-Z0-9\/._-]+\.(?:mp4|webm))\)/',
            static function (array $matches) use (&$htmlBlocks): string {
                $path = $matches[1];
                $type = str_ends_with(strtolower($path), '.webm') ? 'video/webm' : 'video/mp4';
                $placeholder = '%%HTML_BLOCK_' . count($htmlBlocks) . '%%';
                $htmlBlocks[$placeholder] = '<a class="media-link media-video-link" href="' . $path . '" data-media-popup="1"><video controls preload="metadata" aria-label="Uploaded video"><source src="' . $path . '" type="' . $type . '"></video></a>';

                return $placeholder;
            },
            $escaped
        ) ?? $escaped;
        $escaped = preg_replace_callback(
            '/\[embedded video\]\((https?:\/\/[^\s)]+)\)/',
            function (array $matches) use (&$htmlBlocks): string {
                $placeholder = '%%HTML_BLOCK_' . count($htmlBlocks) . '%%';
                $htmlBlocks[$placeholder] = $this->renderExternalVideo((string) $matches[1]);

                return $placeholder;
            },
            $escaped
        ) ?? $escaped;
        $escaped = preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
            function (array $matches) use (&$htmlBlocks): string {
                $placeholder = '%%HTML_BLOCK_' . count($htmlBlocks) . '%%';
                $url = html_entity_decode((string) $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $safeUrl = safe_external_url($url);
                if ($safeUrl === '') {
                    return $matches[0];
                }
                $htmlBlocks[$placeholder] = '<a href="' . h($safeUrl) . '" rel="nofollow ugc noopener noreferrer">' . $matches[1] . '</a>';

                return $placeholder;
            },
            $escaped
        ) ?? $escaped;
        $escaped = $this->renderMentions($escaped);
        if ($codeBlocks !== []) {
            $escaped = strtr($escaped, $codeBlocks);
        }
        if ($htmlBlocks !== []) {
            $escaped = strtr($escaped, $htmlBlocks);
        }

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

    private function renderMentions(string $escaped): string
    {
        return preg_replace_callback(
            '/(^|[\s>(])@([a-z0-9][a-z0-9_-]{2,31})\b/i',
            function (array $matches): string {
                $prefix = (string) $matches[1];
                $username = (string) $matches[2];
                $slug = $this->mentionSlug($username);
                if ($slug === null) {
                    return $matches[0];
                }

                return $prefix . '<a href="/user/' . h(rawurlencode($slug)) . '" class="user-mention">@' . h($slug) . '</a>';
            },
            $escaped
        ) ?? $escaped;
    }

    private function mentionSlug(string $username): ?string
    {
        $key = strtolower($username);
        if (array_key_exists($key, $this->mentionSlugs)) {
            return $this->mentionSlugs[$key];
        }

        if ($this->users === null) {
            $this->mentionSlugs[$key] = null;

            return null;
        }

        $user = $this->users->findMentionableByUsername($username);
        $slug = is_array($user) ? (string) ($user['username'] ?? '') : '';
        $this->mentionSlugs[$key] = $slug !== '' ? $slug : null;

        return $this->mentionSlugs[$key];
    }

    private function renderExternalVideo(string $url): string
    {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $embed = $this->externalVideoEmbedUrl($url);
        if ($embed === null) {
            $safeUrl = safe_external_url($url);
            if ($safeUrl === '') {
                return h($url);
            }

            return '<a href="' . h($safeUrl) . '" rel="nofollow ugc noopener noreferrer">' . h($safeUrl) . '</a>';
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
        return preg_match('/^(?:<a class="media-link[^"]*" href="\/public-assets\/(?:images|videos)\/[^"]+" data-media-popup="1">.*<\/a>|<div class="video-embed">.*<\/div>)$/s', $block) === 1;
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
