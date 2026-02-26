<?php

declare(strict_types=1);

use App\Services\BillingPayments\PayPalPaymentProviderAdapter;
use App\Services\BillingPayments\PaymentProviderRegistry;
use App\Services\BillingPayments\StripePaymentProviderAdapter;
use App\Services\BillingPaymentsService;

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

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
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

$registry = new PaymentProviderRegistry([
    new StripePaymentProviderAdapter(),
    new PayPalPaymentProviderAdapter(),
]);

$stripePayload = $registry->resolve('stripe')->createPaymentLink([
    'payment_link_id' => 'plink_123',
    'url' => 'https://checkout.stripe.com/pay/plink_123',
], ['currency_code' => 'EUR']);
assertSame('plink_123', $stripePayload['payment_link_id'], 'Stripe link id mapping failed.');
assertSame('open', $stripePayload['status'], 'Stripe default status must be open.');

$paypalPayload = $registry->resolve('paypal')->createPaymentLink([
    'order_id' => 'ORDER-42',
    'approval_url' => 'https://www.paypal.com/checkoutnow?token=ORDER-42',
], ['currency_code' => 'EUR']);
assertSame('ORDER-42', $paypalPayload['payment_link_id'], 'PayPal order mapping failed.');
assertSame('https://www.paypal.com/checkoutnow?token=ORDER-42', $paypalPayload['payment_url'], 'PayPal approval url mapping failed.');

assertThrows(static fn () => $registry->resolve('adyen'), 'invalid_provider');

$service = new BillingPaymentsService(new PDO('sqlite::memory:'), $registry);
assertTrue($service->isDunningEscalationDue(null), 'Dunning escalation should be due for a new case.');
assertTrue(!$service->isDunningEscalationDue(['last_notice_at' => date('Y-m-d') . ' 08:00:00']), 'Dunning escalation should be throttled on the same day.');
assertTrue($service->isDunningEscalationDue(['last_notice_at' => date('Y-m-d', strtotime('-1 day')) . ' 23:59:59']), 'Dunning escalation should be due on next day.');

echo "Billing payments Phase-2 regression checks passed\n";
