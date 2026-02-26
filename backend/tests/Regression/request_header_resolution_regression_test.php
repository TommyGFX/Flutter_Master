<?php

declare(strict_types=1);

use App\Core\Request;

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

$request = new Request();

$serverBackup = $_SERVER;

$_SERVER = [
    'HTTP_ORIGIN' => 'https://crm.ordentis.de',
];
assertSame('https://crm.ordentis.de', $request->header('Origin'), 'Must resolve HTTP_ORIGIN.');

$_SERVER = [
    'ORIGIN' => 'https://crm.ordentis.de',
];
assertSame('https://crm.ordentis.de', $request->header('Origin'), 'Must resolve ORIGIN fallback key.');

$_SERVER = [
    'REDIRECT_HTTP_ORIGIN' => 'https://crm.ordentis.de',
];
assertSame('https://crm.ordentis.de', $request->header('Origin'), 'Must resolve redirect origin fallback key.');

$_SERVER = [];
assertSame(null, $request->header('Origin'), 'Missing headers should return null.');

$_SERVER = $serverBackup;

echo "Request header resolution regression checks passed\n";
