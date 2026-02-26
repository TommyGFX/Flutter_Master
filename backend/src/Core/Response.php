<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }

    public static function streamDownload(string $filename, string $contentType, callable $writer, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $writer();
    }
}
