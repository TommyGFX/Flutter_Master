<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

App\Core\Env::load(__DIR__ . '/../../.env');
set_exception_handler([App\Core\ErrorHandler::class, 'handle']);
set_error_handler([App\Core\ErrorHandler::class, 'handlePhpError']);
