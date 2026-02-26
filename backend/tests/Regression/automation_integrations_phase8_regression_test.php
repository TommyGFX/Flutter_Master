<?php

declare(strict_types=1);

use App\Services\AutomationIntegrationsService;

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
$pdo->exec('CREATE TABLE automation_workflow_runs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, provider TEXT, trigger_key TEXT, action_key TEXT, payload_json TEXT, run_status TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE automation_import_products (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, sku TEXT, name TEXT, description TEXT, unit_price REAL, tax_rate REAL, created_at TEXT)');
$pdo->exec('CREATE TABLE automation_import_historical_invoices (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, source_id TEXT, document_number TEXT, customer_name TEXT, currency_code TEXT, grand_total REAL, issued_on TEXT, due_on TEXT, created_at TEXT)');
$pdo->exec('CREATE TABLE billing_customers (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, customer_type TEXT, company_name TEXT, first_name TEXT, last_name TEXT, email TEXT, phone TEXT, vat_id TEXT, currency_code TEXT)');

$service = new AutomationIntegrationsService($pdo);

$queued = $service->enqueueAutomationRun('tenant_phase8', [
    'provider' => 'zapier',
    'trigger_key' => 'invoice.finalized',
    'action_key' => 'sync.crm',
    'payload' => ['event' => 'invoice.finalized'],
]);
assertSame('queued', $queued['status'] ?? null, 'Enqueued run should be queued.');

$processed = $service->processAutomationRuns('tenant_phase8', 10);
assertSame(1, $processed['processed'] ?? null, 'Worker should process one queued run.');
assertSame(1, $processed['completed'] ?? null, 'Worker should mark run as completed.');
assertSame('completed', $processed['results'][0]['status'] ?? null, 'Result payload should expose completed status.');

$dbStatus = $pdo->query("SELECT run_status FROM automation_workflow_runs WHERE id = 1")->fetchColumn();
assertSame('completed', $dbStatus, 'Worker must persist completed run status.');

$preview = $service->previewImport('tenant_phase8', [
    'dataset' => 'customers',
    'rows' => [
        ['company_name' => 'Acme GmbH', 'email' => 'finance@acme.example'],
        ['company_name' => ''],
    ],
]);
assertTrue(($preview['can_import'] ?? false) === true, 'Import preview should allow valid customer rows.');
assertSame(1, $preview['invalid_rows'] ?? null, 'Preview should classify invalid rows.');

$execute = $service->executeImport('tenant_phase8', [
    'dataset' => 'customers',
    'rows' => [
        ['company_name' => 'Acme GmbH', 'email' => 'finance@acme.example'],
        ['first_name' => 'Max', 'last_name' => 'Mustermann', 'email' => 'max@example.com'],
    ],
]);
assertSame(2, $execute['imported_rows'] ?? null, 'Execution should import valid customer rows.');

$count = (int) $pdo->query("SELECT COUNT(*) FROM billing_customers WHERE tenant_id = 'tenant_phase8'")->fetchColumn();
assertSame(2, $count, 'Execute import should write customer records.');

echo "Automation Integrations Phase-8 worker/import regression checks passed\n";
