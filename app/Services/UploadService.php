<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final readonly class UploadService
{
    /** @param array<string, mixed> $file */
    public function storeImage(array $file, string $directory = 'public-assets/images/uploads'): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }

        if ((int) ($file['size'] ?? 0) > 2_000_000) {
            throw new RuntimeException('Upload is too large.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new RuntimeException('Upload is invalid.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif', 'image/gif' => 'gif'];
        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Unsupported file type.');
        }

        $imageInfo = $this->withoutWarnings(static fn (): array|false => getimagesize($tmp));
        if (!is_array($imageInfo) || (int) ($imageInfo[0] ?? 0) < 1 || (int) ($imageInfo[1] ?? 0) < 1) {
            throw new RuntimeException('Upload is not a valid image.');
        }

        if ((int) $imageInfo[0] > 6000 || (int) $imageInfo[1] > 6000) {
            throw new RuntimeException('Image dimensions are too large.');
        }

        if (str_contains($directory, '..') || str_contains($directory, "\0")) {
            throw new RuntimeException('Upload path is invalid.');
        }

        $targetDir = BASE_PATH . '/' . trim($directory, '/');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0750, true);
        }

        $baseName = bin2hex(random_bytes(16));
        $converted = $this->storeModernFormats($tmp, $mime, $targetDir, $baseName);
        if ($converted !== null) {
            return '/' . trim($directory, '/') . '/' . $converted;
        }

        $name = $baseName . '.' . $extensions[$mime];
        $target = $targetDir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Unable to store upload.');
        }
        chmod($target, 0644);

        return '/' . trim($directory, '/') . '/' . $name;
    }

    /** @param array<string, mixed> $file */
    public function storeMedia(array $file, string $directory = 'public-assets/images/uploads'): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }

        // Size check comes first — before any file reading — so oversized uploads
        // are rejected without touching the filesystem or running GD.
        if ((int) ($file['size'] ?? 0) > 12_000_000) {
            throw new RuntimeException('Upload is too large.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new RuntimeException('Upload is invalid.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        if (str_starts_with($mime, 'image/')) {
            return $this->storeImage($file, $directory);
        }

        $extensions = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Unsupported file type.');
        }

        if (str_contains($directory, '..') || str_contains($directory, "\0")) {
            throw new RuntimeException('Upload path is invalid.');
        }

        $targetDir = BASE_PATH . '/' . trim($directory, '/');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0750, true);
        }

        $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $target = $targetDir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Unable to store upload.');
        }
        chmod($target, 0644);

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
                'image/gif' => function_exists('imagecreatefromgif') ? $this->withoutWarnings(static fn (): \GdImage|false => imagecreatefromgif($tmp)) : false,
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
            // Remove the webp if avif was successfully written to save disk space
            if ($webpOk && is_file($webpTarget)) {
                @unlink($webpTarget);
            }
            return $avif;
        }
        if ($webpOk && is_file($webpTarget)) {
            return $webp;
        }

        return null;
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
