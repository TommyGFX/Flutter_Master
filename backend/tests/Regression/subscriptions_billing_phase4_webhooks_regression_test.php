<?php

declare(strict_types=1);

use App\Services\SubscriptionsBillingService;

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

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE subscription_contracts (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, payment_method_ref TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE subscription_payment_method_updates (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, contract_id INTEGER, provider TEXT, token TEXT, update_url TEXT, status TEXT, completed_at TEXT)');
$pdo->exec('CREATE TABLE subscription_dunning_cases (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, contract_id INTEGER, payment_method_update_required INTEGER, status TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE subscription_cycles (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, contract_id INTEGER, event_type TEXT, amount_delta REAL, currency_code TEXT, metadata_json TEXT)');

$pdo->exec("INSERT INTO subscription_contracts (id, tenant_id, payment_method_ref) VALUES (41, 'tenant_webhooks', 'pm_old')");
$pdo->exec("INSERT INTO subscription_payment_method_updates (tenant_id, contract_id, provider, token, update_url, status) VALUES ('tenant_webhooks', 41, 'stripe', 'tokstripe', 'https://example', 'open')");
$pdo->exec("INSERT INTO subscription_payment_method_updates (tenant_id, contract_id, provider, token, update_url, status) VALUES ('tenant_webhooks', 41, 'paypal', 'tokpaypal', 'https://example', 'open')");
$pdo->exec("INSERT INTO subscription_dunning_cases (tenant_id, contract_id, payment_method_update_required, status) VALUES ('tenant_webhooks', 41, 1, 'failed')");

$service = new SubscriptionsBillingService($pdo);

$callback = $service->completePaymentMethodUpdate('tenant_webhooks', [
    'provider' => 'stripe',
    'token' => 'tokstripe',
    'status' => 'completed',
    'payment_method_ref' => 'pm_new_callback',
]);
assertSame('completed', $callback['status'], 'Completion callback should mark update as completed.');

$contractRef = $pdo->query("SELECT payment_method_ref FROM subscription_contracts WHERE id = 41")->fetchColumn();
assertSame('pm_new_callback', $contractRef, 'Completion callback must update contract payment method.');

$paypalWebhookPayload = json_encode([
    'event_type' => 'BILLING.SUBSCRIPTION.APPROVED',
    'tenant_id' => 'tenant_webhooks',
    'resource' => [
        'custom_id' => 'tokpaypal',
        'payer_id' => 'payer_live_sandbox_42',
    ],
], JSON_THROW_ON_ERROR);

putenv('SUBSCRIPTIONS_PAYPAL_WEBHOOK_SECRET=paypal_secret');
$paypalSignature = hash_hmac('sha256', $paypalWebhookPayload, 'paypal_secret');
$paypalResult = $service->handleProviderWebhook('paypal', $paypalWebhookPayload, $paypalSignature);
assertSame('completed', $paypalResult['status'], 'PayPal webhook completion should be processed.');
assertSame('payer_live_sandbox_42', $paypalResult['payment_method_ref'], 'PayPal payer id mapping failed.');

$stripeWebhookPayload = json_encode([
    'type' => 'setup_intent.succeeded',
    'data' => [
        'object' => [
            'payment_method' => 'pm_stripe_sandbox_99',
            'metadata' => [
                'tenant_id' => 'tenant_webhooks',
                'token' => 'tokstripe',
            ],
        ],
    ],
], JSON_THROW_ON_ERROR);

putenv('SUBSCRIPTIONS_STRIPE_WEBHOOK_SECRET=stripe_secret');
$stripeSignature = hash_hmac('sha256', $stripeWebhookPayload, 'stripe_secret');
$stripeResult = $service->handleProviderWebhook('stripe', $stripeWebhookPayload, $stripeSignature);
assertSame('completed', $stripeResult['status'], 'Stripe webhook completion should be processed.');

$cycleCount = (int) $pdo->query('SELECT COUNT(*) FROM subscription_cycles')->fetchColumn();
assertTrue($cycleCount >= 3, 'Completion callback + both webhooks should append cycle audit entries.');

echo "Subscriptions Billing Phase-4 webhook/completion regression checks passed\n";
