<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final readonly class UploadService
{
    /** @param array<string, mixed> $file */
    public function storeImage(array $file, string $directory = 'public-assets/images/uploads'): string
    {
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif', 'image/gif' => 'gif'];
        ['tmp' => $tmp, 'mime' => $mime] = $this->validatedUpload($file, 2_000_000);
        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Unsupported file type.');
        }

        $this->assertSafeImage($tmp, $mime);
        $targetDir = $this->targetDirectory($directory);

        $baseName = bin2hex(random_bytes(16));
        // Preserve GIFs as GIFs so animated uploads do not get flattened by GD re-encoding.
        $converted = $mime === 'image/gif' ? null : $this->storeModernFormats($tmp, $mime, $targetDir, $baseName);
        if ($converted !== null) {
            return '/' . trim($directory, '/') . '/' . $converted;
        }

        $name = $baseName . '.' . $extensions[$mime];
        $this->moveUpload($tmp, $targetDir . DIRECTORY_SEPARATOR . $name);

        return '/' . trim($directory, '/') . '/' . $name;
    }

    /** @param array<string, mixed> $file */
    public function storeLogo(array $file, string $directory = 'public-assets/images/logos'): string
    {
        ['tmp' => $tmp, 'mime' => $mime] = $this->validatedUpload($file, 2_000_000);
        if ($mime === 'image/svg+xml') {
            $this->assertSafeSvg($tmp);
            $targetDir = $this->targetDirectory($directory);
            $name = bin2hex(random_bytes(16)) . '.svg';
            $this->moveUpload($tmp, $targetDir . DIRECTORY_SEPARATOR . $name);

            return '/' . trim($directory, '/') . '/' . $name;
        }

        return $this->storeImage($file, $directory);
    }

    /** @param array<string, mixed> $file */
    public function storeSocialImage(array $file, string $directory = 'public-assets/images/social'): string
    {
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        ['tmp' => $tmp, 'mime' => $mime] = $this->validatedUpload($file, 2_000_000);
        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Social sharing images must be JPG, PNG, WEBP, or GIF.');
        }

        $this->assertSafeImage($tmp, $mime);
        $targetDir = $this->targetDirectory($directory);
        $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $this->moveUpload($tmp, $targetDir . DIRECTORY_SEPARATOR . $name);

        return '/' . trim($directory, '/') . '/' . $name;
    }

    /** @param array<string, mixed> $file */
    public function storeMedia(array $file, string $directory = 'public-assets/images/uploads'): string
    {
        ['tmp' => $tmp, 'mime' => $mime] = $this->validatedUpload($file, 12_000_000);
        if (str_starts_with($mime, 'image/')) {
            return $this->storeImage($file, $directory);
        }

        $extensions = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Unsupported file type.');
        }

        $this->assertSafeVideo($tmp, $mime);
        $targetDir = $this->targetDirectory($directory);

        $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $this->moveUpload($tmp, $targetDir . DIRECTORY_SEPARATOR . $name);

        return '/' . trim($directory, '/') . '/' . $name;
    }

    /** @param array<string, mixed> $file @return array{tmp: string, mime: string, original_name: string} */
    private function validatedUpload(array $file, int $maxBytes): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
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

        return ['tmp' => $tmp, 'mime' => $mime, 'original_name' => $originalName];
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
            imagedestroy($image);
            foreach ([$webpTarget, $avifTarget] as $partial) {
                if (is_file($partial)) {
                    unlink($partial);
                }
            }
            return null;
        }
        imagedestroy($image);

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

        if ((int) $imageInfo[0] > 6000 || (int) $imageInfo[1] > 6000) {
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
                if ($this->isSvgMime($mime, $tmp)) {
                    return 'image/svg+xml';
                }
                return $mime;
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($tmp);
            if (is_string($mime) && $mime !== '') {
                if ($this->isSvgMime($mime, $tmp)) {
                    return 'image/svg+xml';
                }
                return $mime;
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

        $lower = strtolower($contents);
        foreach (['<?php', '<?=', '<%', '#!/bin/', '#!/usr/bin/', 'this program cannot be run in dos mode'] as $needle) {
            if (str_contains($lower, $needle)) {
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
        foreach (['<script', 'javascript:', 'data:text/html', 'data:application', 'onload=', 'onerror=', 'onclick=', 'onmouseover=', '<foreignobject', '<iframe', '<object', '<embed'] as $needle) {
            if (str_contains($lower, $needle)) {
                throw new RuntimeException('SVG contains unsafe content.');
            }
        }

        $xml = simplexml_load_string($contents);
        if (!$xml instanceof \SimpleXMLElement || strtolower((string) $xml->getName()) !== 'svg') {
            throw new RuntimeException('Upload is not a valid SVG.');
        }
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
