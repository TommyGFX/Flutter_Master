<?php

declare(strict_types=1);

use App\Services\StripeService;
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

function assertThrows(callable $fn, string $expectedMessage): void
{
    try {
        $fn();
    } catch (InvalidArgumentException $exception) {
        assertSame($expectedMessage, $exception->getMessage(), 'Unexpected exception message.');
        return;
    }

    throw new RuntimeException('Expected InvalidArgumentException with message: ' . $expectedMessage);
}

$serviceReflection = new ReflectionClass(StripeService::class);
$service = $serviceReflection->newInstanceWithoutConstructor();
$method = $serviceReflection->getMethod('resolveCustomerPortalReturnUrl');
$method->setAccessible(true);

$_ENV['STRIPE_PORTAL_RETURN_URL'] = 'https://crm.example.com/portal';
$_ENV['STRIPE_CHECKOUT_SUCCESS_URL'] = 'https://crm.example.com/success';
assertSame(
    'https://crm.example.com/portal',
    $method->invoke($service, null),
    'Should prefer STRIPE_PORTAL_RETURN_URL when available.'
);

unset($_ENV['STRIPE_PORTAL_RETURN_URL']);
$_ENV['STRIPE_CHECKOUT_SUCCESS_URL'] = 'https://crm.example.com/success';
assertSame(
    'https://crm.example.com/success',
    $method->invoke($service, null),
    'Should fallback to STRIPE_CHECKOUT_SUCCESS_URL when STRIPE_PORTAL_RETURN_URL is missing.'
);

unset($_ENV['STRIPE_PORTAL_RETURN_URL'], $_ENV['STRIPE_CHECKOUT_SUCCESS_URL']);
assertThrows(
    static fn () => $method->invoke($service, null),
    'Fehlende Umgebungsvariable: STRIPE_PORTAL_RETURN_URL (alternativ STRIPE_CHECKOUT_SUCCESS_URL).'
);

assertSame(
    'https://request.example.com/return',
    $method->invoke($service, 'https://request.example.com/return'),
    'Request payload return URL should override env config.'
);

echo "Stripe portal return URL fallback regression checks passed\n";
