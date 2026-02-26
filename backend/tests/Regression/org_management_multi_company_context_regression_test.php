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

$pdo->exec('CREATE TABLE org_companies (
    tenant_id TEXT NOT NULL,
    company_id TEXT NOT NULL,
    name TEXT NOT NULL,
    legal_name TEXT NULL,
    tax_number TEXT NULL,
    vat_id TEXT NULL,
    currency_code TEXT NOT NULL,
    is_active INTEGER NOT NULL,
    updated_at TEXT NULL
)');
$pdo->exec('CREATE TABLE org_company_memberships (
    tenant_id TEXT NOT NULL,
    company_id TEXT NOT NULL,
    user_id TEXT NOT NULL,
    role_key TEXT NOT NULL,
    updated_at TEXT NULL
)');
$pdo->exec('CREATE TABLE role_permissions (tenant_id TEXT NOT NULL, role_key TEXT NOT NULL, permission_key TEXT NOT NULL)');
$pdo->exec('CREATE TABLE roles (tenant_id TEXT NOT NULL, role_key TEXT NOT NULL, name TEXT NOT NULL)');
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
    created_at TEXT NULL
)');

$pdo->exec("INSERT INTO org_companies (tenant_id, company_id, name, currency_code, is_active) VALUES
    ('tenant_1', 'company_a', 'Acme A', 'EUR', 1),
    ('tenant_1', 'company_b', 'Acme B', 'EUR', 1)");
$pdo->exec("INSERT INTO org_company_memberships (tenant_id, company_id, user_id, role_key) VALUES
    ('tenant_1', 'company_a', 'user_1', 'admin')");
$pdo->exec("INSERT INTO roles (tenant_id, role_key, name) VALUES ('tenant_1', 'admin', 'Admin'), ('tenant_1', 'readonly', 'Read-only')");
$pdo->exec("INSERT INTO role_permissions (tenant_id, role_key, permission_key) VALUES
    ('tenant_1', 'admin', '*'),
    ('tenant_1', 'readonly', 'org.read')");

$databaseReflection = new ReflectionClass(Database::class);
$pdoProperty = $databaseReflection->getProperty('pdo');
$pdoProperty->setAccessible(true);
$pdoProperty->setValue(null, $pdo);

$service = new OrgManagementService($pdo);
$controller = new OrgManagementController($service, new RbacService(), new AuditLogService());
$request = new Request();

$_SERVER['REQUEST_URI'] = '/api/org/companies/company_a/memberships';
$_SERVER['HTTP_X_TENANT_ID'] = 'tenant_1';
$_SERVER['HTTP_X_PERMISSIONS'] = 'org.read,org.manage';
ob_start();
$controller->listMemberships($request, 'company_a');
$membershipsPayload = json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
assertSameValue(1, count($membershipsPayload['data'] ?? []), 'Membership list should return existing member.');

$pdo->exec("INSERT INTO org_company_memberships (tenant_id, company_id, user_id, role_key) VALUES ('tenant_1', 'company_b', 'user_1', 'readonly')");
$context = $service->switchCompanyContext('tenant_1', 'user_1', 'company_b');
assertSameValue('company_b', $context['company_id'] ?? null, 'Context switch should target company_b.');
assertSameValue('readonly', $context['role_key'] ?? null, 'Role should resolve from membership.');
assertSameValue(['org.read'], $context['permissions'] ?? [], 'Resolved permissions should be returned for switched context.');

echo "Org management multi-company context regression checks passed\n";
