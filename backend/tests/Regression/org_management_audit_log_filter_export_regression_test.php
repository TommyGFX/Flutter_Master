<?php

declare(strict_types=1);

use App\Controllers\OrgManagementController;
use App\Core\Database;
use App\Core\Request;
use App\Services\AuditLogService;
use App\Services\OrgManagementService;
use App\Services\RbacService;

require __DIR__ . '/../../src/Core/bootstrap.php';
set_exception_handler(null);
restore_error_handler();

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected: ' . var_export($expected, true) . ', got: ' . var_export($actual, true));
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id TEXT NOT NULL,
    company_id TEXT NULL,
    actor_id TEXT NOT NULL,
    action_key TEXT NOT NULL,
    target_type TEXT NOT NULL,
    target_id TEXT NOT NULL,
    status TEXT NOT NULL,
    metadata_json TEXT NULL,
    ip_address TEXT NULL,
    user_agent TEXT NULL,
    created_at TEXT NOT NULL
)');

$pdo->exec("INSERT INTO audit_logs
    (tenant_id, company_id, actor_id, action_key, target_type, target_id, status, metadata_json, ip_address, user_agent, created_at)
VALUES
    ('tenant_1', 'company_a', 'actor_1', 'org.role.upsert', 'role', 'admin', 'success', '{}', '127.0.0.1', 'agent', '2026-01-01T10:00:00Z'),
    ('tenant_1', 'company_b', 'actor_2', 'org.membership.upsert', 'company_membership', 'company_b', 'failed', '{}', '127.0.0.2', 'agent', '2026-01-02T10:00:00Z'),
    ('tenant_2', 'company_x', 'actor_3', 'org.context.switch', 'company', 'company_x', 'success', '{}', '127.0.0.3', 'agent', '2026-01-03T10:00:00Z')");

$databaseReflection = new ReflectionClass(Database::class);
$pdoProperty = $databaseReflection->getProperty('pdo');
$pdoProperty->setAccessible(true);
$pdoProperty->setValue(null, $pdo);

$controller = new OrgManagementController(new OrgManagementService($pdo), new RbacService(), new AuditLogService());
$request = new Request();

$_SERVER['REQUEST_URI'] = '/api/org/audit-logs?company_id=company_a&status=success&actor_id=actor_1&action_key=org.role.upsert&from=2026-01-01T00:00:00Z&to=2026-01-01T23:59:59Z&limit=10';
$_SERVER['HTTP_X_TENANT_ID'] = 'tenant_1';
$_SERVER['HTTP_X_PERMISSIONS'] = 'audit.read,audit.export';

ob_start();
$controller->listAuditLogs($request);
$listOutput = (string) ob_get_clean();
$listPayload = json_decode($listOutput, true, flags: JSON_THROW_ON_ERROR);
assertSameValue(1, count($listPayload['data'] ?? []), 'Filtered audit log list should contain exactly one row.');
assertSameValue('company_a', $listPayload['data'][0]['company_id'] ?? null, 'Audit list filter by company failed.');

$_SERVER['REQUEST_URI'] = '/api/org/audit-logs/export?company_id=company_a&status=success&actor_id=actor_1&action_key=org.role.upsert';
ob_start();
$controller->exportAuditLogs($request);
$exportOutput = (string) ob_get_clean();
$exportPayload = json_decode($exportOutput, true, flags: JSON_THROW_ON_ERROR);

assertSameValue(1, $exportPayload['data']['rows'] ?? 0, 'Audit export row count should respect filters.');
if (strpos((string) ($exportPayload['data']['content'] ?? ''), 'company_a') === false) {
    throw new RuntimeException('Audit export CSV content should contain filtered company row.');
}

echo "Org management audit log filter/export regression checks passed\n";
