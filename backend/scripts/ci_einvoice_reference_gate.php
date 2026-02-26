<?php

declare(strict_types=1);

use App\Services\BillingCoreService;
use App\Services\TaxComplianceDeService;

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $path = __DIR__ . '/../src/' . $relative . '.php';
        if (file_exists($path)) {
            require $path;
        }
    });
}

function resolveConfigValue(string $baseKey, ?string $environment): ?string
{
    if (is_string($environment) && trim($environment) !== '') {
        $scopedValue = getenv($baseKey . '_' . strtoupper(trim($environment)));
        if (is_string($scopedValue) && trim($scopedValue) !== '') {
            return trim($scopedValue);
        }
    }

    $defaultValue = getenv($baseKey);
    if (!is_string($defaultValue) || trim($defaultValue) === '') {
        return null;
    }

    return trim($defaultValue);
}

function runExternalValidator(string $url, string $format, string $xml, ?string $authHeader, ?string $authToken, int $timeoutSeconds): void
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('could_not_init_curl');
    }

    $headers = ['Content-Type: application/xml', 'Accept: application/json'];
    if (is_string($authToken) && $authToken !== '') {
        $headerName = is_string($authHeader) && trim($authHeader) !== '' ? trim($authHeader) : 'Authorization';
        $headers[] = sprintf('%s: %s', $headerName, $authToken);
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeoutSeconds,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException(sprintf('%s_validator_request_failed:%s', $format, $error));
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException(sprintf('%s_validator_http_%d:%s', $format, $httpCode, $response));
    }

    $decoded = json_decode($response, true);
    if (is_array($decoded)) {
        $valid = $decoded['valid'] ?? $decoded['isValid'] ?? null;
        if ($valid === false) {
            throw new RuntimeException(sprintf('%s_validator_rejected:%s', $format, json_encode($decoded, JSON_THROW_ON_ERROR)));
        }
    }
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

$tenantId = 'tenant-ci-gate';
$pdo->prepare('INSERT INTO tenant_tax_profiles (tenant_id, business_name, tax_number, vat_id, small_business_enabled, default_tax_category, supply_date_required, service_date_required, country_code, updated_at) VALUES (:tenant_id, :business_name, :tax_number, :vat_id, 0, :default_tax_category, 1, 0, :country_code, CURRENT_TIMESTAMP)')
    ->execute([
        'tenant_id' => $tenantId,
        'business_name' => 'Ordentis GmbH',
        'tax_number' => 'DE123/456/789',
        'vat_id' => 'DE999999999',
        'default_tax_category' => 'standard',
        'country_code' => 'DE',
    ]);

$pdo->prepare('INSERT INTO billing_documents (tenant_id, document_type, document_number, status, customer_name_snapshot, currency_code, grand_total, due_date, reference_document_id, totals_json) VALUES (:tenant_id, :document_type, :document_number, :status, :customer_name_snapshot, :currency_code, :grand_total, :due_date, NULL, :totals_json)')
    ->execute([
        'tenant_id' => $tenantId,
        'document_type' => 'invoice',
        'document_number' => 'INV-CI-1',
        'status' => 'sent',
        'customer_name_snapshot' => 'Bundesdruckerei GmbH',
        'currency_code' => 'EUR',
        'grand_total' => 119.0,
        'due_date' => '2026-03-01',
        'totals_json' => '{}',
    ]);
$documentId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO billing_line_items (tenant_id, document_id, description, quantity, unit_price, tax_rate) VALUES (:tenant_id, :document_id, :description, :quantity, :unit_price, :tax_rate)')
    ->execute([
        'tenant_id' => $tenantId,
        'document_id' => $documentId,
        'description' => 'SaaS Lizenz',
        'quantity' => 1,
        'unit_price' => 100,
        'tax_rate' => 19,
    ]);

$pdo->prepare('INSERT INTO billing_document_addresses (tenant_id, document_id, address_type, company_name, street, postal_code, city, country) VALUES (:tenant_id, :document_id, :address_type, :company_name, :street, :postal_code, :city, :country)')
    ->execute([
        'tenant_id' => $tenantId,
        'document_id' => $documentId,
        'address_type' => 'billing',
        'company_name' => 'Bundesdruckerei GmbH',
        'street' => 'Kommandantenstr. 18',
        'postal_code' => '10969',
        'city' => 'Berlin',
        'country' => 'DE',
    ]);

$service = new TaxComplianceDeService($pdo, new BillingCoreService($pdo));

$validatorEnvironment = resolveConfigValue('EINVOICE_VALIDATOR_ENV', null);
$timeoutSeconds = (int) (resolveConfigValue('EINVOICE_VALIDATOR_TIMEOUT_SECONDS', $validatorEnvironment) ?? '30');
$timeoutSeconds = $timeoutSeconds > 0 ? $timeoutSeconds : 30;

$formats = ['xrechnung', 'zugferd'];
foreach ($formats as $format) {
    $export = $service->exportEInvoice($tenantId, $documentId, $format);
    if (($export['validation']['valid'] ?? false) !== true) {
        throw new RuntimeException('internal_validation_failed_for_' . $format);
    }

    $xml = base64_decode((string) ($export['content_base64'] ?? ''), true);
    if (!is_string($xml) || $xml === '') {
        throw new RuntimeException('missing_export_xml_for_' . $format);
    }

    $validatorPrefix = strtoupper($format) . '_VALIDATOR';
    $validatorUrl = resolveConfigValue($validatorPrefix . '_URL', $validatorEnvironment);
    if (is_string($validatorUrl) && $validatorUrl !== '') {
        $authHeader = resolveConfigValue($validatorPrefix . '_AUTH_HEADER', $validatorEnvironment);
        $authToken = resolveConfigValue($validatorPrefix . '_AUTH_TOKEN', $validatorEnvironment);

        runExternalValidator($validatorUrl, $format, $xml, $authHeader, $authToken, $timeoutSeconds);
        echo strtoupper($format) . " external validator passed (env=" . ($validatorEnvironment ?? 'default') . ")\n";
        continue;
    }

    echo strtoupper($format) . " external validator skipped (set " . $validatorPrefix . "_URL";
    if ($validatorEnvironment !== null) {
        echo '_' . strtoupper($validatorEnvironment);
    }
    echo ")\n";
}

echo "E-Invoice reference validator CI gate passed\n";
