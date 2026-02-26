<?php

declare(strict_types=1);

use App\Services\SubscriptionsBilling\PayPalPaymentMethodUpdateProviderAdapter;
use App\Services\SubscriptionsBilling\PaymentMethodUpdateProviderRegistry;
use App\Services\SubscriptionsBilling\StripePaymentMethodUpdateProviderAdapter;

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

function assertStringContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . ' needle=' . $needle . ' haystack=' . $haystack);
    }
}

function assertThrows(callable $fn, string $expectedMessage): void
{
    try {
        $fn();
    } catch (RuntimeException $exception) {
        assertSame($expectedMessage, $exception->getMessage(), 'Unexpected exception message.');
        return;
    }

    throw new RuntimeException('Expected RuntimeException with message: ' . $expectedMessage);
}

$registry = new PaymentMethodUpdateProviderRegistry([
    new StripePaymentMethodUpdateProviderAdapter(),
    new PayPalPaymentMethodUpdateProviderAdapter(),
]);

assertSame(['stripe', 'paypal'], $registry->availableProviders(), 'Provider list should contain stripe and paypal in registration order.');

$stripeLink = $registry->resolve('stripe')->createUpdateLink('tenant_a', 42, 'abc123', ['payment_method_ref' => 'pm_123'], []);
assertSame('open', $stripeLink['status'], 'Stripe update link must be open.');
assertStringContains('/stripe/payment-method-update?', $stripeLink['update_url'], 'Stripe path mapping mismatch.');
assertStringContains('contract=42', $stripeLink['update_url'], 'Stripe contract parameter missing.');

$paypalLink = $registry->resolve('paypal')->createUpdateLink('tenant_a', 42, 'def456', ['payment_method_ref' => 'payer_42'], ['base_url' => 'https://custom.example']);
assertStringContains('/paypal/payment-method-update?', $paypalLink['update_url'], 'PayPal path mapping mismatch.');
assertStringContains('custom.example', $paypalLink['update_url'], 'PayPal custom base url should be applied.');

assertThrows(static fn () => $registry->resolve('adyen'), 'invalid_provider');

echo "Subscriptions Billing Phase-4 provider adapter checks passed\n";
