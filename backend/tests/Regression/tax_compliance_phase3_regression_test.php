<?php

declare(strict_types=1);

use App\Services\BillingCoreService;
use App\Services\TaxComplianceDeService;

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

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertContains(string $needle, array $haystack, string $message): void
{
    if (!in_array($needle, $haystack, true)) {
        throw new RuntimeException($message . ' missing=' . $needle . ' values=' . json_encode($haystack, JSON_THROW_ON_ERROR));
    }
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
    } catch (RuntimeException $exception) {
        assertSame($expectedMessage, $exception->getMessage(), 'Unexpected exception message.');
        return;
    }

    throw new RuntimeException('Expected RuntimeException with message: ' . $expectedMessage);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$schema = [
    'CREATE TABLE billing_documents (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, document_type TEXT, document_number TEXT, status TEXT, customer_name_snapshot TEXT, currency_code TEXT, grand_total NUMERIC, due_date TEXT, reference_document_id INTEGER, totals_json TEXT)',
    'CREATE TABLE billing_line_items (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, document_id INTEGER, description TEXT, quantity NUMERIC, unit_price NUMERIC, tax_rate NUMERIC)',
    'CREATE TABLE billing_tax_breakdowns (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, document_id INTEGER, tax_rate NUMERIC, tax_amount NUMERIC, net_amount NUMERIC, gross_amount NUMERIC)',
    'CREATE TABLE billing_document_addresses (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, document_id INTEGER, address_type TEXT, company_name TEXT, first_name TEXT, last_name TEXT, street TEXT, house_number TEXT, postal_code TEXT, city TEXT, country TEXT, email TEXT, phone TEXT)',
    'CREATE TABLE tenant_tax_profiles (tenant_id TEXT PRIMARY KEY, business_name TEXT, tax_number TEXT, vat_id TEXT, small_business_enabled INTEGER, default_tax_category TEXT, supply_date_required INTEGER, service_date_required INTEGER, country_code TEXT, updated_at TEXT)',
    'CREATE TABLE billing_document_compliance (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, document_id INTEGER, plugin_key TEXT, is_sealed INTEGER, seal_hash TEXT, sealed_at TEXT, preflight_status TEXT, preflight_report_json TEXT, correction_of_document_id INTEGER, correction_reason TEXT, updated_at TEXT)',
    'CREATE TABLE billing_einvoice_exchange (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, document_id INTEGER, exchange_direction TEXT, invoice_format TEXT, payload_json TEXT, xml_content TEXT, status TEXT, created_at TEXT)',
];

foreach ($schema as $statement) {
    $pdo->exec($statement);
}

$tenantId = 'tenant-phase3';
$pdo->prepare('INSERT INTO tenant_tax_profiles (tenant_id, business_name, tax_number, vat_id, small_business_enabled, default_tax_category, supply_date_required, service_date_required, country_code, updated_at) VALUES (:tenant_id, :business_name, :tax_number, :vat_id, :small_business_enabled, :default_tax_category, :supply_date_required, :service_date_required, :country_code, CURRENT_TIMESTAMP)')
    ->execute([
        'tenant_id' => $tenantId,
        'business_name' => 'Ordentis GmbH',
        'tax_number' => 'DE123',
        'vat_id' => '',
        'small_business_enabled' => 0,
        'default_tax_category' => 'standard',
        'supply_date_required' => 1,
        'service_date_required' => 0,
        'country_code' => 'DE',
    ]);

$insertDocument = $pdo->prepare('INSERT INTO billing_documents (tenant_id, document_type, document_number, status, customer_name_snapshot, currency_code, grand_total, due_date, reference_document_id, totals_json) VALUES (:tenant_id, :document_type, :document_number, :status, :customer_name_snapshot, :currency_code, :grand_total, :due_date, :reference_document_id, :totals_json)');
$insertLine = $pdo->prepare('INSERT INTO billing_line_items (tenant_id, document_id, description, quantity, unit_price, tax_rate) VALUES (:tenant_id, :document_id, :description, :quantity, :unit_price, :tax_rate)');
$insertAddress = $pdo->prepare('INSERT INTO billing_document_addresses (tenant_id, document_id, address_type, company_name, country) VALUES (:tenant_id, :document_id, :address_type, :company_name, :country)');

$insertDocument->execute([
    'tenant_id' => $tenantId,
    'document_type' => 'invoice',
    'document_number' => 'INV-1',
    'status' => 'sent',
    'customer_name_snapshot' => 'Acme GmbH',
    'currency_code' => 'EUR',
    'grand_total' => 119.0,
    'due_date' => null,
    'reference_document_id' => null,
    'totals_json' => '{}',
]);
$invoiceId = (int) $pdo->lastInsertId();
$insertLine->execute(['tenant_id' => $tenantId, 'document_id' => $invoiceId, 'description' => 'Abo', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 19]);
$insertAddress->execute(['tenant_id' => $tenantId, 'document_id' => $invoiceId, 'address_type' => 'billing', 'company_name' => 'Acme GmbH', 'country' => 'DE']);

$insertDocument->execute([
    'tenant_id' => $tenantId,
    'document_type' => 'credit_note',
    'document_number' => 'CRN-1',
    'status' => 'sent',
    'customer_name_snapshot' => 'Acme GmbH',
    'currency_code' => 'EUR',
    'grand_total' => 119.0,
    'due_date' => '2026-01-10',
    'reference_document_id' => null,
    'totals_json' => '{}',
]);
$creditId = (int) $pdo->lastInsertId();
$insertLine->execute(['tenant_id' => $tenantId, 'document_id' => $creditId, 'description' => 'Korrektur', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 19]);
$insertAddress->execute(['tenant_id' => $tenantId, 'document_id' => $creditId, 'address_type' => 'billing', 'company_name' => 'Acme GmbH', 'country' => 'DE']);

$insertDocument->execute([
    'tenant_id' => $tenantId,
    'document_type' => 'invoice',
    'document_number' => 'INV-2',
    'status' => 'sent',
    'customer_name_snapshot' => 'Acme GmbH',
    'currency_code' => 'EUR',
    'grand_total' => 238.0,
    'due_date' => '2026-01-31',
    'reference_document_id' => null,
    'totals_json' => '{}',
]);
$validInvoiceId = (int) $pdo->lastInsertId();
$insertLine->execute(['tenant_id' => $tenantId, 'document_id' => $validInvoiceId, 'description' => 'Projektarbeit', 'quantity' => 2, 'unit_price' => 100, 'tax_rate' => 19]);
$insertAddress->execute(['tenant_id' => $tenantId, 'document_id' => $validInvoiceId, 'address_type' => 'billing', 'company_name' => 'Acme GmbH', 'country' => 'DE']);

$insertDocument->execute([
    'tenant_id' => $tenantId,
    'document_type' => 'invoice',
    'document_number' => 'INV-RC-1',
    'status' => 'sent',
    'customer_name_snapshot' => 'EU-Customer Sp. z o.o.',
    'currency_code' => 'EUR',
    'grand_total' => 100.0,
    'due_date' => '2026-02-15',
    'reference_document_id' => null,
    'totals_json' => '{}',
]);
$reverseChargeInvoiceId = (int) $pdo->lastInsertId();
$insertLine->execute(['tenant_id' => $tenantId, 'document_id' => $reverseChargeInvoiceId, 'description' => 'EU Beratung', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0]);
$insertAddress->execute(['tenant_id' => $tenantId, 'document_id' => $reverseChargeInvoiceId, 'address_type' => 'billing', 'company_name' => 'EU-Customer Sp. z o.o.', 'country' => 'PL']);

$service = new TaxComplianceDeService($pdo, new BillingCoreService($pdo));

$invoicePreflight = $service->preflightDocument($tenantId, $invoiceId);
assertTrue(($invoicePreflight['valid'] ?? true) === false, 'Invoice without due date should fail preflight.');
assertContains('missing_due_date', $invoicePreflight['errors'], 'Invoice preflight must include due date error.');

$creditPreflight = $service->preflightDocument($tenantId, $creditId);
assertTrue(($creditPreflight['valid'] ?? true) === false, 'Credit note without negative totals/reference should fail preflight.');
assertContains('missing_reference_document', $creditPreflight['errors'], 'Credit note preflight must require reference document.');
assertContains('credit_or_cancellation_requires_negative_total', $creditPreflight['errors'], 'Credit note preflight must require negative totals.');
assertContains('credit_or_cancellation_requires_negative_line_items', $creditPreflight['errors'], 'Credit note preflight must require negative line items.');

$reverseChargePreflight = $service->preflightDocument($tenantId, $reverseChargeInvoiceId);
assertContains('intra_community', $reverseChargePreflight['tax_categories'], 'Cross-border EU zero-tax lines should be classified as intra-community.');
assertContains('intra_community_requires_seller_vat_id', $reverseChargePreflight['errors'], 'Intra-community flow must require seller VAT ID for compliance.');

$pdo->prepare('UPDATE tenant_tax_profiles SET vat_id = :vat_id WHERE tenant_id = :tenant_id')->execute([
    'tenant_id' => $tenantId,
    'vat_id' => 'DE999999999',
]);
$reverseChargePreflightWithVat = $service->preflightDocument($tenantId, $reverseChargeInvoiceId);
assertTrue(($reverseChargePreflightWithVat['valid'] ?? false) === true, 'Cross-border EU case should pass when seller VAT ID is set.');

$export = $service->exportEInvoice($tenantId, $validInvoiceId, 'xrechnung');
assertTrue(($export['validation']['valid'] ?? false) === true, 'XRechnung export should pass XML validator.');
assertSame('xrechnung', $export['format'], 'Export should keep requested format.');

$importValid = $service->importEInvoice($tenantId, ['format' => 'zugferd', 'xml_content' => base64_decode($service->exportEInvoice($tenantId, $validInvoiceId, 'zugferd')['content_base64'], true)]);
assertSame('validated', $importValid['status'], 'ZUGFeRD import should be validated.');

assertThrows(static fn () => $service->importEInvoice($tenantId, [
    'format' => 'xrechnung',
    'xml_content' => '<eInvoice><format>zugferd</format></eInvoice>',
]), 'invalid_einvoice_xml');

echo "Tax compliance Phase-3 regression checks passed\n";
