<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;

final class UploadController
{
    public function uploadImage(): void
    {
        $this->handleUpload(['image/jpeg', 'image/png', 'image/webp'], 'images');
    }

    public function uploadFile(): void
    {
        $this->handleUpload([
            'application/pdf',
            'application/zip',
            'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ], 'files');
    }

    /** @param string[] $allowed */
    private function handleUpload(array $allowed, string $folder): void
    {
        if (!isset($_FILES['file'])) {
            Response::json(['error' => 'file_missing'], 422);
            return;
        }

        $file = $_FILES['file'];
        $mime = mime_content_type($file['tmp_name']);
        if ($mime === false || !in_array($mime, $allowed, true)) {
            Response::json(['error' => 'invalid_mimetype', 'detected' => $mime], 422);
            return;
        }

        $targetDir = __DIR__ . '/../../storage/uploads/' . $folder;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $name = uniqid('upload_', true) . '_' . basename((string) $file['name']);
        $targetPath = $targetDir . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            Response::json(['error' => 'upload_failed'], 500);
            return;
        }

        Response::json(['uploaded' => true, 'path' => $name, 'mime' => $mime]);
    }
}
