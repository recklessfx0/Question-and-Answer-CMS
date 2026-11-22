<?php
class UploadService {
    public static function upload(array $file, string $dir): string {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Upload failed");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowed[$mime])) {
            throw new Exception("Invalid file type");
        }

        $ext = $allowed[$mime];
        $name = bin2hex(random_bytes(16)) . "." . $ext;
        $path = $dir . "/" . $name;

        $data = file_get_contents($file['tmp_name']);
        $img = imagecreatefromstring($data);

        if (!$img) {
            throw new Exception("Invalid image");
        }

        imagejpeg($img, $path, 90);
        unset($img);

        chmod($path, 0644);

        return $name;
    }
}
