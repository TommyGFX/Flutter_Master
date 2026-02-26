<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class ErrorHandler
{
    public static function handle(Throwable $throwable): void
    {
        http_response_code(500);
        header('Content-Type: application/json');

        $debug = Env::get('APP_DEBUG', 'false') === 'true';
        echo json_encode([
            'error' => 'internal_server_error',
            'message' => $debug ? $throwable->getMessage() : 'Ein interner Fehler ist aufgetreten.',
        ], JSON_THROW_ON_ERROR);

        $logPath = __DIR__ . '/../../storage/logs/error.log';
        @file_put_contents($logPath, sprintf("[%s] %s\n", date('c'), $throwable), FILE_APPEND);
    }

    public static function handlePhpError(int $severity, string $message, string $file, int $line): bool
    {
        if (($severity & (E_DEPRECATED | E_USER_DEPRECATED)) !== 0) {
            return true;
        }

        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
}
