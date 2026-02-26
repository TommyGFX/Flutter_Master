<?php

declare(strict_types=1);

use App\Services\FinanceReportingService;

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
$pdo->exec('CREATE TABLE billing_documents (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, document_number TEXT, document_type TEXT, finalized_at TEXT, grand_total REAL, due_date TEXT, status TEXT, customer_id INTEGER)');
$pdo->exec('CREATE TABLE billing_payments (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, document_id INTEGER, status TEXT, amount REAL)');
$pdo->exec('CREATE TABLE billing_customers (id INTEGER PRIMARY KEY AUTOINCREMENT, company_name TEXT, first_name TEXT, last_name TEXT)');
$pdo->exec('CREATE TABLE billing_dunning_cases (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, document_id INTEGER, current_level INTEGER)');
$pdo->exec('CREATE TABLE billing_tax_breakdowns (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, document_id INTEGER, tax_rate REAL, taxable_net REAL, tax_amount REAL, gross_amount REAL)');
$pdo->exec('CREATE TABLE subscription_plans (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, billing_interval TEXT, amount REAL)');
$pdo->exec('CREATE TABLE subscription_contracts (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, plan_id INTEGER, status TEXT)');
$pdo->exec('CREATE TABLE finance_reporting_connectors (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, provider TEXT, webhook_url TEXT, credentials_json TEXT, is_enabled INTEGER, updated_at TEXT)');
$pdo->exec('CREATE TABLE finance_reporting_webhook_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, provider TEXT, webhook_url TEXT, payload_json TEXT, delivery_status TEXT, created_at TEXT)');

$pdo->exec("INSERT INTO billing_customers (id, company_name) VALUES (10, 'Acme GmbH')");
$pdo->exec("INSERT INTO billing_documents (id, tenant_id, document_number, document_type, finalized_at, grand_total, due_date, status, customer_id) VALUES (1, 'tenant_phase6', 'INV-1', 'invoice', '2026-01-10', 120.5, '2026-01-20', 'due', 10)");
$pdo->exec("INSERT INTO billing_payments (tenant_id, document_id, status, amount) VALUES ('tenant_phase6', 1, 'received', 20.5)");
$pdo->exec("INSERT INTO billing_tax_breakdowns (tenant_id, document_id, tax_rate, taxable_net, tax_amount, gross_amount) VALUES ('tenant_phase6', 1, 19.0, 100.0, 19.0, 119.0)");

$service = new FinanceReportingService($pdo);

$stream = $service->buildExportStream('tenant_phase6', 'datev', 'excel', '2026-01-01', '2026-01-31');
assertSame('text/tab-separated-values; charset=utf-8', $stream['content_type'] ?? null, 'Excel stream should use TSV content type for robust spreadsheet import.');
assertTrue(is_callable($stream['stream_writer'] ?? null), 'Export stream must provide a writer callback.');

ob_start();
$writer = $stream['stream_writer'];
$writer();
$exportBody = (string) ob_get_clean();
assertTrue(str_contains($exportBody, "belegnummer\tbelegtyp\tbuchungsdatum\tbetrag\toffen"), 'Streamed export should include DATEV header row.');
assertTrue(str_contains($exportBody, 'INV-1'), 'Streamed export should include document rows.');

$pdo->exec("INSERT INTO finance_reporting_connectors (tenant_id, provider, webhook_url, credentials_json, is_enabled) VALUES ('tenant_phase6', 'lexoffice', NULL, '{}', 1)");
$pdo->exec("INSERT INTO finance_reporting_webhook_logs (tenant_id, provider, webhook_url, payload_json, delivery_status, created_at) VALUES ('tenant_phase6', 'lexoffice', NULL, '{\"id\":1}', 'queued', '2026-02-01 10:00:00')");

$sync = $service->syncConnectors('tenant_phase6', 10);
assertSame(1, $sync['processed'] ?? null, 'Sync should pick queued connector jobs.');
assertSame(1, $sync['failed'] ?? null, 'Sync must mark entries without webhook URL as failed.');
assertSame('failed', $sync['results'][0]['status'] ?? null, 'Failed delivery should be reflected in result payload.');

$dbStatus = $pdo->query("SELECT delivery_status FROM finance_reporting_webhook_logs WHERE tenant_id = 'tenant_phase6' AND id = 1")->fetchColumn();
assertSame('failed', $dbStatus, 'Sync should persist failed delivery status for retry visibility.');

echo "Finance Reporting Phase-6 hardening regression checks passed\n";
