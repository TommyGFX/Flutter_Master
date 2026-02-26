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


$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE billing_documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id TEXT NOT NULL,
    status TEXT NOT NULL,
    grand_total REAL NOT NULL,
    currency_code TEXT NOT NULL,
    due_date TEXT NULL
)');
$pdo->exec('CREATE TABLE billing_payment_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id TEXT NOT NULL,
    document_id INTEGER NOT NULL,
    provider TEXT NOT NULL,
    payment_link_id TEXT NOT NULL,
    payment_url TEXT NOT NULL,
    status TEXT NOT NULL,
    provider_response_json TEXT NULL,
    amount REAL NOT NULL,
    currency_code TEXT NOT NULL,
    expires_at TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
)');
$pdo->exec("INSERT INTO billing_documents (id, tenant_id, status, grand_total, currency_code, due_date) VALUES (1, 'tenant-phase2', 'sent', 120.00, 'EUR', date('now', '-10 day'))");

$serviceWithData = new BillingPaymentsService($pdo, $registry);
$stripeLink = $serviceWithData->createPaymentLink('tenant-phase2', 1, [
    'provider' => 'stripe',
    'payment_link_id' => 'plink_e2e_1',
    'url' => 'https://checkout.stripe.com/pay/plink_e2e_1',
    'provider_response' => ['id' => 'plink_e2e_1', 'object' => 'payment_link'],
]);
assertSame('stripe', $stripeLink['provider'], 'Stripe service flow should keep provider key.');
assertTrue(str_contains((string) $stripeLink['provider_response_json'], 'payment_link'), 'Stripe provider response should be JSON encoded.');

$paypalLink = $serviceWithData->createPaymentLink('tenant-phase2', 1, [
    'provider' => 'paypal',
    'order_id' => 'ORDER-E2E-1',
    'approval_url' => 'https://www.paypal.com/checkoutnow?token=ORDER-E2E-1',
    'provider_response' => ['id' => 'ORDER-E2E-1', 'intent' => 'CAPTURE'],
]);
assertSame('paypal', $paypalLink['provider'], 'PayPal service flow should keep provider key.');

$links = $serviceWithData->listPaymentLinks('tenant-phase2', 1);
assertSame(2, count($links), 'Service should persist payment links for both adapters.');
assertTrue(str_contains((string) $links[0]['provider_response_json'], 'CAPTURE') || str_contains((string) $links[1]['provider_response_json'], 'CAPTURE'), 'PayPal provider response should be persisted for audits.');

$service = new BillingPaymentsService(new PDO('sqlite::memory:'), $registry);
assertTrue($service->isDunningEscalationDue(null), 'Dunning escalation should be due for a new case.');
assertTrue(!$service->isDunningEscalationDue(['last_notice_at' => date('Y-m-d') . ' 08:00:00']), 'Dunning escalation should be throttled on the same day.');
assertTrue($service->isDunningEscalationDue(['last_notice_at' => date('Y-m-d', strtotime('-1 day')) . ' 23:59:59']), 'Dunning escalation should be due on next day.');

assertSame('partial', $service->derivePaymentKind(50.0, 120.0), 'Partial settlement kind should be derived.');
assertSame('full', $service->derivePaymentKind(120.0, 120.0), 'Full settlement kind should be derived.');
assertSame('overpayment', $service->derivePaymentKind(130.0, 120.0), 'Overpayment kind should be derived.');

$flatInterest = $service->calculateDunningInterest(1000.0, date('Y-m-d', strtotime('-20 days')), 5.0, 3, 0, 'flat', 0.0);
assertSame(50.0, $flatInterest, 'Flat interest should be 5% of outstanding amount.');

$dailyInterest = $service->calculateDunningInterest(1000.0, date('Y-m-d', strtotime('-20 days')), 7.3, 3, 5, 'daily_pro_rata', 0.0);
assertTrue($dailyInterest > 2.3 && $dailyInterest < 2.5, 'Daily pro-rata interest should use overdue days after grace and free days.');

$cappedInterest = $service->calculateDunningInterest(1000.0, date('Y-m-d', strtotime('-50 days')), 20.0, 0, 0, 'flat', 30.0);
assertSame(30.0, $cappedInterest, 'Interest cap should limit computed interest.');

$noInterestWithinGrace = $service->calculateDunningInterest(1000.0, date('Y-m-d', strtotime('-2 days')), 7.3, 3, 0, 'daily_pro_rata', 0.0);
assertSame(0.0, $noInterestWithinGrace, 'No interest should accrue within grace period.');

echo "Billing payments Phase-2 regression checks passed\n";
