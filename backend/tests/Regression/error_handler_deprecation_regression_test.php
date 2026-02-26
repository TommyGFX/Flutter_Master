<?php

declare(strict_types=1);

use App\Core\ErrorHandler;

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $path = __DIR__ . '/../../src/' . $relative . '.php';
        if (file_exists($path)) {
            require $path;
        }
    });
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

$deprecatedHandled = ErrorHandler::handlePhpError(E_DEPRECATED, 'deprecated', __FILE__, __LINE__);
assertSame(true, $deprecatedHandled, 'Deprecated errors must be swallowed by the global error handler.');

$didThrow = false;
try {
    ErrorHandler::handlePhpError(E_WARNING, 'warning', __FILE__, __LINE__);
} catch (ErrorException $exception) {
    $didThrow = true;
}
assertSame(true, $didThrow, 'Non-deprecated PHP errors must still throw ErrorException.');

echo "ErrorHandler deprecation regression checks passed\n";
