<?php

declare(strict_types=1);

use App\Core\App;

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

$app = new App();
$ref = new ReflectionClass($app);
$method = $ref->getMethod('isAllowedCorsOrigin');
$method->setAccessible(true);

assertSame(true, $method->invoke($app, 'https://crm.ordentis.de'), 'CRM origin must be allowed.');
assertSame(true, $method->invoke($app, 'https://api.ordentis.de'), 'API origin must be allowed.');
assertSame(true, $method->invoke($app, 'https://staging.ordentis.de'), 'Subdomain origin must be allowed.');
assertSame(true, $method->invoke($app, 'http://localhost:4200'), 'Localhost any port must be allowed.');
assertSame(false, $method->invoke($app, 'https://evil.example.com'), 'Unknown origin must be blocked.');

print "CORS origin regression checks passed\n";
