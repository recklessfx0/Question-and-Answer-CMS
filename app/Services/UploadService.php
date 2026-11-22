<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class UploadService
{
    private const DEFAULT_IMAGE_DIRECTORY = 'public-assets/images/uploads';
    private const DEFAULT_VIDEO_DIRECTORY = 'public-assets/videos/uploads';
    private const MAX_IMAGE_WIDTH = 12_000;
    private const MAX_IMAGE_HEIGHT = 12_000;
    private const MAX_IMAGE_PIXELS = 50_000_000;
    private const MAX_IMAGE_BYTES = 8_000_000;
    private const MAX_LOGO_BYTES = 2_000_000;
    private const MAX_SOCIAL_IMAGE_BYTES = 5_000_000;
    private const MAX_POST_IMAGE_BYTES = 12_000_000;
    private const MAX_POST_VIDEO_BYTES = 40_000_000;

    /** @param array<string, mixed> $file */
    public function storeImage(array $file, string $directory = self::DEFAULT_IMAGE_DIRECTORY, ?int $maxBytes = null): string
    {
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif', 'image/gif' => 'gif'];
        ['tmp' => $tmp, 'mime' => $mime] = $this->validatedUpload($file, $maxBytes ?? self::MAX_IMAGE_BYTES);
        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Unsupported file type.');
        }

        return $this->storeValidatedImage($tmp, $mime, $directory, $extensions);
    }

    /** @param array<string, mixed> $file */
    public function storeLogo(array $file, string $directory = 'public-assets/images/logos'): string
    {
        ['tmp' => $tmp, 'mime' => $mime] = $this->validatedUpload($file, self::MAX_LOGO_BYTES);
        if ($mime === 'image/svg+xml') {
            $this->assertSafeSvg($tmp);
            $targetDir = $this->targetDirectory($directory);
            $name = bin2hex(random_bytes(16)) . '.svg';
            $this->moveUpload($tmp, $targetDir . DIRECTORY_SEPARATOR . $name);

            return '/' . trim($directory, '/') . '/' . $name;
        }

        return $this->storeImage($file, $directory, self::MAX_LOGO_BYTES);
    }

    /** @param array<string, mixed> $file */
    public function storeSocialImage(array $file, string $directory = 'public-assets/images/social'): string
    {
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        ['tmp' => $tmp, 'mime' => $mime] = $this->validatedUpload($file, self::MAX_SOCIAL_IMAGE_BYTES);
        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Social sharing images must be JPG, PNG, WEBP, or GIF.');
        }

        return $this->storeValidatedImage($tmp, $mime, $directory, $extensions);
    }

    /** @param array<string, mixed> $file */
    public function storeMedia(array $file, string $imageDirectory = self::DEFAULT_IMAGE_DIRECTORY, string $videoDirectory = self::DEFAULT_VIDEO_DIRECTORY): string
    {
        ['tmp' => $tmp, 'mime' => $mime, 'size' => $size] = $this->validatedUpload($file, max(self::MAX_POST_IMAGE_BYTES, self::MAX_POST_VIDEO_BYTES));
        if (str_starts_with($mime, 'image/')) {
            if ($size > self::MAX_POST_IMAGE_BYTES) {
                throw new RuntimeException('Image uploads must be 12 MB or smaller.');
            }

            return $this->storeValidatedImage($tmp, $mime, $imageDirectory, ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif', 'image/gif' => 'gif']);
        }

        $extensions = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Unsupported file type.');
        }
        if ($size > self::MAX_POST_VIDEO_BYTES) {
            throw new RuntimeException('Video uploads must be 40 MB or smaller.');
        }

        $this->assertSafeVideo($tmp, $mime);
        return $this->storeValidatedVideo($tmp, $mime, $videoDirectory, $extensions);
    }

    /** @param array<string, mixed> $file @return array{tmp: string, mime: string, original_name: string, size: int} */
    private function validatedUpload(array $file, int $maxBytes): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($error));
        }

        // Reject oversized uploads before touching the contents.
        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            throw new RuntimeException('Upload is too large.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !$this->isUploadedFile($tmp)) {
            throw new RuntimeException('Upload is invalid.');
        }

        $originalName = $this->normalizedOriginalName((string) ($file['name'] ?? ''));
        if ($this->hasForbiddenOriginalExtension($originalName)) {
            throw new RuntimeException('Executable or script uploads are not allowed.');
        }

        $mime = $this->detectMimeType($tmp);
        if ($mime === '') {
            throw new RuntimeException('Upload type could not be detected.');
        }

        $this->assertNoExecutablePayload($tmp, $mime);

        return ['tmp' => $tmp, 'mime' => $mime, 'original_name' => $originalName, 'size' => (int) ($file['size'] ?? 0)];
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Upload is too large.',
            UPLOAD_ERR_PARTIAL => 'Upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Choose a file to upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Upload is temporarily unavailable on the server.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not store the uploaded file.',
            UPLOAD_ERR_EXTENSION => 'The upload was blocked by a server extension.',
            default => 'Upload failed.',
        };
    }

    /** @param array<string, string> $extensions */
    private function storeValidatedImage(string $tmp, string $mime, string $directory, array $extensions): string
    {
        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Unsupported file type.');
        }

        $this->assertSafeImage($tmp, $mime);
        $targetDir = $this->targetDirectory($directory);
        $baseName = bin2hex(random_bytes(16));
        $converted = $mime === 'image/gif' ? null : $this->storeModernFormats($tmp, $mime, $targetDir, $baseName);
        if ($converted !== null) {
            return '/' . trim($directory, '/') . '/' . $converted;
        }

        $name = $baseName . '.' . $extensions[$mime];
        $this->moveUpload($tmp, $targetDir . DIRECTORY_SEPARATOR . $name);

        return '/' . trim($directory, '/') . '/' . $name;
    }

    /** @param array<string, string> $extensions */
    private function storeValidatedVideo(string $tmp, string $mime, string $directory, array $extensions): string
    {
        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Unsupported file type.');
        }

        $targetDir = $this->targetDirectory($directory);
        $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $this->moveUpload($tmp, $targetDir . DIRECTORY_SEPARATOR . $name);

        return '/' . trim($directory, '/') . '/' . $name;
    }

    private function storeModernFormats(string $tmp, string $mime, string $targetDir, string $baseName): ?string
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp') || !function_exists('imageavif')) {
            return null;
        }

        try {
            $image = match ($mime) {
                'image/jpeg' => function_exists('imagecreatefromjpeg') ? $this->withoutWarnings(static fn (): \GdImage|false => imagecreatefromjpeg($tmp)) : false,
                'image/png' => function_exists('imagecreatefrompng') ? $this->withoutWarnings(static fn (): \GdImage|false => imagecreatefrompng($tmp)) : false,
                'image/webp' => function_exists('imagecreatefromwebp') ? $this->withoutWarnings(static fn (): \GdImage|false => imagecreatefromwebp($tmp)) : false,
                'image/avif' => function_exists('imagecreatefromavif') ? $this->withoutWarnings(static fn (): \GdImage|false => imagecreatefromavif($tmp)) : false,
                default => false,
            };
        } catch (RuntimeException) {
            return null;
        }

        if (!$image instanceof \GdImage) {
            return null;
        }

        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($image);
        }
        if (function_exists('imagealphablending') && function_exists('imagesavealpha')) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        $webp = $baseName . '.webp';
        $avif = $baseName . '.avif';
        $webpTarget = $targetDir . DIRECTORY_SEPARATOR . $webp;
        $avifTarget = $targetDir . DIRECTORY_SEPARATOR . $avif;

        try {
            $webpOk = $this->withoutWarnings(static fn (): bool => imagewebp($image, $webpTarget, 82));
            $avifOk = $this->withoutWarnings(static fn (): bool => imageavif($image, $avifTarget, 50));
        } catch (RuntimeException) {
            unset($image);
            foreach ([$webpTarget, $avifTarget] as $partial) {
                if (is_file($partial)) {
                    unlink($partial);
                }
            }
            return null;
        }
        unset($image);

        if ($webpOk && is_file($webpTarget)) {
            chmod($webpTarget, 0644);
        }
        if ($avifOk && is_file($avifTarget)) {
            chmod($avifTarget, 0644);
        }

        if ($avifOk && is_file($avifTarget)) {
            return $avif;
        }
        if ($webpOk && is_file($webpTarget)) {
            return $webp;
        }

        return null;
    }

    private function assertSafeImage(string $tmp, string $mime): void
    {
        $imageInfo = $this->withoutWarnings(static fn (): array|false => getimagesize($tmp));
        if (!is_array($imageInfo) || (int) ($imageInfo[0] ?? 0) < 1 || (int) ($imageInfo[1] ?? 0) < 1) {
            throw new RuntimeException('Upload is not a valid image.');
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if (
            $width > self::MAX_IMAGE_WIDTH
            || $height > self::MAX_IMAGE_HEIGHT
            || ($width * $height) > self::MAX_IMAGE_PIXELS
        ) {
            throw new RuntimeException('Image dimensions are too large.');
        }

        $detectedMime = $this->detectedImageMime($tmp, $imageInfo);
        if ($detectedMime === '' || $detectedMime !== $mime) {
            throw new RuntimeException('Upload is not a valid image.');
        }
    }

    private function assertSafeVideo(string $tmp, string $mime): void
    {
        $head = $this->readBytes($tmp, 64);
        if ($mime === 'video/mp4') {
            if (strlen($head) < 12 || substr($head, 4, 4) !== 'ftyp') {
                throw new RuntimeException('Upload is not a valid MP4 video.');
            }
            return;
        }

        if ($mime === 'video/webm' && !str_starts_with($head, "\x1A\x45\xDF\xA3")) {
            throw new RuntimeException('Upload is not a valid WebM video.');
        }
    }

    private function detectMimeType(string $tmp): string
    {
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp);
            if (is_string($mime) && $mime !== '') {
                $normalized = $this->normalizedDetectedMime($mime, $tmp);
                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($tmp);
            if (is_string($mime) && $mime !== '') {
                $normalized = $this->normalizedDetectedMime($mime, $tmp);
                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        $imageMime = $this->detectedImageMime($tmp);
        if ($imageMime !== '') {
            return $imageMime;
        }

        $head = $this->readBytes($tmp, 64);
        if (strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp') {
            return 'video/mp4';
        }
        if (str_starts_with($head, "\x1A\x45\xDF\xA3")) {
            return 'video/webm';
        }

        return '';
    }

    private function normalizedDetectedMime(string $mime, string $tmp): string
    {
        $mime = strtolower(trim($mime));
        if ($mime === '') {
            return '';
        }

        if ($this->isSvgMime($mime, $tmp)) {
            return 'image/svg+xml';
        }

        $allowed = [
            'image/jpeg' => true,
            'image/png' => true,
            'image/gif' => true,
            'image/webp' => true,
            'image/avif' => true,
            'video/mp4' => true,
            'video/webm' => true,
        ];

        return isset($allowed[$mime]) ? $mime : '';
    }

    private function isSvgMime(string $mime, string $tmp): bool
    {
        if (in_array(strtolower($mime), ['image/svg+xml', 'image/svg', 'text/xml', 'application/xml', 'text/plain'], true)) {
            $head = strtolower($this->readBytes($tmp, 512));

            return str_contains($head, '<svg') || str_contains($head, '<?xml');
        }

        return false;
    }

    /** @param array<int|string, mixed>|null $imageInfo */
    private function detectedImageMime(string $tmp, ?array $imageInfo = null): string
    {
        if (function_exists('exif_imagetype')) {
            $imageType = @exif_imagetype($tmp);
            if (is_int($imageType)) {
                if (defined('IMAGETYPE_AVIF') && $imageType === constant('IMAGETYPE_AVIF')) {
                    return 'image/avif';
                }

                return match ($imageType) {
                    IMAGETYPE_JPEG => 'image/jpeg',
                    IMAGETYPE_PNG => 'image/png',
                    IMAGETYPE_GIF => 'image/gif',
                    IMAGETYPE_WEBP => 'image/webp',
                    default => '',
                };
            }
        }

        $imageInfo ??= @getimagesize($tmp);
        $mime = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
        return in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'], true) ? $mime : '';
    }

    private function assertNoExecutablePayload(string $tmp, string $mime): void
    {
        if (!str_starts_with($mime, 'image/') && !str_starts_with($mime, 'video/')) {
            throw new RuntimeException('Unsupported file type.');
        }

        $contents = file_get_contents($tmp);
        if (!is_string($contents) || $contents === '') {
            throw new RuntimeException('Upload is invalid.');
        }

        // Scan only the leading bytes for script markers so normal binary media does not
        // get rejected because compressed payload bytes coincidentally contain short tag
        // sequences such as "<%".
        $prefix = strtolower(substr($contents, 0, 4096));
        foreach (['<?php', '<?=', '#!/bin/', '#!/usr/bin/', 'this program cannot be run in dos mode'] as $needle) {
            if (str_contains($prefix, $needle)) {
                throw new RuntimeException('Executable or script uploads are not allowed.');
            }
        }

        if (str_starts_with($contents, 'MZ') || str_starts_with($contents, "\x7FELF") || str_starts_with($contents, "#!")) {
            throw new RuntimeException('Executable uploads are not allowed.');
        }
    }

    private function assertSafeSvg(string $tmp): void
    {
        $contents = file_get_contents($tmp);
        if (!is_string($contents) || $contents === '' || strlen($contents) > 2_000_000) {
            throw new RuntimeException('Upload is invalid.');
        }

        $lower = strtolower($contents);
        foreach (['<script', '<!doctype', '<!entity', 'javascript:', 'vbscript:', 'data:', '<foreignobject', '<iframe', '<object', '<embed'] as $needle) {
            if (str_contains($lower, $needle)) {
                throw new RuntimeException('SVG contains unsafe content.');
            }
        }
        if (preg_match('/\son[a-z0-9_-]+\s*=/i', $contents) === 1) {
            throw new RuntimeException('SVG contains unsafe content.');
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$xml instanceof \SimpleXMLElement || strtolower((string) $xml->getName()) !== 'svg') {
            throw new RuntimeException('Upload is not a valid SVG.');
        }

        $this->assertSafeSvgElement($xml);
    }

    private function assertSafeSvgElement(\SimpleXMLElement $element): void
    {
        $allowedElements = [
            'svg' => true,
            'g' => true,
            'path' => true,
            'rect' => true,
            'circle' => true,
            'ellipse' => true,
            'line' => true,
            'polyline' => true,
            'polygon' => true,
            'text' => true,
            'tspan' => true,
            'defs' => true,
            'lineargradient' => true,
            'radialgradient' => true,
            'stop' => true,
            'clippath' => true,
            'mask' => true,
            'title' => true,
            'desc' => true,
        ];
        $name = $this->svgLocalName((string) $element->getName());
        if (!isset($allowedElements[$name])) {
            throw new RuntimeException('SVG contains unsupported content.');
        }

        foreach ($this->svgAttributes($element) as $attribute => $value) {
            $this->assertSafeSvgAttribute($attribute, $value);
        }

        foreach ($element->children() as $child) {
            $this->assertSafeSvgElement($child);
        }
        foreach ($element->getDocNamespaces(true) as $namespace) {
            foreach ($element->children($namespace, true) as $child) {
                $this->assertSafeSvgElement($child);
            }
        }
    }

    /** @return array<string, string> */
    private function svgAttributes(\SimpleXMLElement $element): array
    {
        $attributes = [];
        foreach ($element->attributes() as $name => $value) {
            $attributes[$this->svgLocalName((string) $name)] = (string) $value;
        }
        foreach ($element->getDocNamespaces(true) as $namespace) {
            foreach ($element->attributes($namespace, true) as $name => $value) {
                $attributes[$this->svgLocalName((string) $name)] = (string) $value;
            }
        }

        return $attributes;
    }

    private function assertSafeSvgAttribute(string $attribute, string $value): void
    {
        $allowedAttributes = [
            'id' => true,
            'class' => true,
            'version' => true,
            'xmlns' => true,
            'viewbox' => true,
            'preserveaspectratio' => true,
            'width' => true,
            'height' => true,
            'x' => true,
            'y' => true,
            'x1' => true,
            'y1' => true,
            'x2' => true,
            'y2' => true,
            'cx' => true,
            'cy' => true,
            'r' => true,
            'rx' => true,
            'ry' => true,
            'd' => true,
            'points' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'stroke-linecap' => true,
            'stroke-linejoin' => true,
            'stroke-miterlimit' => true,
            'stroke-dasharray' => true,
            'stroke-dashoffset' => true,
            'opacity' => true,
            'fill-opacity' => true,
            'stroke-opacity' => true,
            'transform' => true,
            'aria-label' => true,
            'role' => true,
            'focusable' => true,
            'space' => true,
            'fill-rule' => true,
            'clip-rule' => true,
            'offset' => true,
            'stop-color' => true,
            'stop-opacity' => true,
            'gradientunits' => true,
            'gradienttransform' => true,
            'clip-path' => true,
            'mask' => true,
            'href' => true,
        ];
        if (!isset($allowedAttributes[$attribute]) || str_starts_with($attribute, 'on')) {
            throw new RuntimeException('SVG contains unsupported attributes.');
        }

        $decoded = strtolower(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (
            preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $decoded) === 1
            || str_contains($decoded, 'javascript:')
            || str_contains($decoded, 'vbscript:')
            || str_contains($decoded, 'data:')
            || str_contains($decoded, 'http://')
            || str_contains($decoded, 'https://')
            || str_contains($decoded, '//')
        ) {
            throw new RuntimeException('SVG contains unsafe attributes.');
        }

        if ($attribute === 'href' && !str_starts_with(trim($decoded), '#')) {
            throw new RuntimeException('SVG contains unsafe references.');
        }
        if (preg_match_all('/url\s*\(([^)]*)\)/i', $decoded, $matches) > 0) {
            foreach ($matches[1] as $reference) {
                $reference = trim((string) $reference, " \t\n\r\0\x0B'\"");
                if (!str_starts_with($reference, '#')) {
                    throw new RuntimeException('SVG contains unsafe references.');
                }
            }
        }
    }

    private function svgLocalName(string $name): string
    {
        $name = strtolower($name);
        $parts = explode(':', $name);

        return (string) end($parts);
    }

    private function targetDirectory(string $directory): string
    {
        if (str_contains($directory, '..') || str_contains($directory, "\0")) {
            throw new RuntimeException('Upload path is invalid.');
        }

        $targetDir = BASE_PATH . '/' . trim($directory, '/');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0750, true);
        }

        return $targetDir;
    }

    private function moveUpload(string $tmp, string $target): void
    {
        $stored = move_uploaded_file($tmp, $target);
        if (!$stored && PHP_SAPI === 'cli') {
            $stored = rename($tmp, $target);
        }

        if (!$stored) {
            throw new RuntimeException('Unable to store upload.');
        }

        chmod($target, 0644);
    }

    private function isUploadedFile(string $tmp): bool
    {
        if (is_uploaded_file($tmp)) {
            return true;
        }

        return PHP_SAPI === 'cli' && is_file($tmp);
    }

    private function normalizedOriginalName(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', '', trim($name));
        return is_string($clean) ? $clean : '';
    }

    private function hasForbiddenOriginalExtension(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        $forbidden = [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
            'cgi', 'pl', 'py', 'sh', 'bash', 'bat', 'cmd', 'com', 'exe', 'msi', 'dll',
            'so', 'jsp', 'asp', 'aspx', 'js', 'jar', 'vb', 'vbs', 'wsf', 'scr', 'hta',
            'htaccess', 'user.ini', 'ini',
        ];

        $parts = array_values(array_filter(explode('.', strtolower($name)), static fn (string $part): bool => $part !== ''));
        foreach ($parts as $part) {
            if (in_array($part, $forbidden, true)) {
                return true;
            }
        }

        return false;
    }

    private function readBytes(string $file, int $length): string
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Upload is invalid.');
        }

        try {
            $bytes = fread($handle, $length);
            return is_string($bytes) ? $bytes : '';
        } finally {
            fclose($handle);
        }
    }

    private function withoutWarnings(callable $callback): mixed
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new RuntimeException($message, $severity);
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }
}
