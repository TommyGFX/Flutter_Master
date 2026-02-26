<?php

declare(strict_types=1);

use App\Services\OrgManagementService;
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

$pdo->exec('CREATE TABLE roles (tenant_id TEXT NOT NULL, role_key TEXT NOT NULL, name TEXT NOT NULL)');
$pdo->exec('CREATE TABLE role_permissions (tenant_id TEXT NOT NULL, role_key TEXT NOT NULL, permission_key TEXT NOT NULL)');
$pdo->exec('CREATE TABLE tenant_plugins (
    tenant_id TEXT NOT NULL,
    plugin_key TEXT NOT NULL,
    display_name TEXT NOT NULL,
    lifecycle_status TEXT NOT NULL,
    is_active INTEGER NOT NULL,
    capabilities_json TEXT NULL,
    required_permissions_json TEXT NULL
)');

$pdo->exec("INSERT INTO roles (tenant_id, role_key, name) VALUES
    ('tenant_1', 'admin', 'Admin'),
    ('tenant_1', 'buchhaltung', 'Buchhaltung'),
    ('tenant_1', 'readonly', 'Read only')");

$pdo->exec("INSERT INTO role_permissions (tenant_id, role_key, permission_key) VALUES
    ('tenant_1', 'admin', '*'),
    ('tenant_1', 'buchhaltung', 'billing.read'),
    ('tenant_1', 'buchhaltung', 'billing.payments.manage'),
    ('tenant_1', 'readonly', 'billing.read')");

$pdo->exec("INSERT INTO tenant_plugins
    (tenant_id, plugin_key, display_name, lifecycle_status, is_active, capabilities_json, required_permissions_json)
VALUES
    ('tenant_1', 'billing_core', 'Billing Core', 'enabled', 1, '[\"billing.documents.read\",\"billing.documents.write\"]', '[\"billing.read\"]'),
    ('tenant_1', 'billing_payments', 'Billing Payments', 'enabled', 1, '[\"billing.payments.capture\"]', '[\"billing.payments.manage\"]'),
    ('tenant_1', 'org_management', 'Org Management', 'suspended', 0, '[\"org.audit.read\"]', '[\"org.read\"]')");

$service = new OrgManagementService($pdo);
$map = $service->listRoleCapabilityMap('tenant_1');

assertSameValue(3, count($map), 'Expected one capability mapping entry per role.');

$byRole = [];
foreach ($map as $entry) {
    $byRole[(string) ($entry['role_key'] ?? '')] = $entry;
}

assertSameValue(2, count($byRole['admin']['plugin_capabilities'] ?? []), 'Admin should see all enabled plugin capabilities.');
assertSameValue(2, count($byRole['buchhaltung']['plugin_capabilities'] ?? []), 'Buchhaltung should see billing and payment capabilities.');
assertSameValue(1, count($byRole['readonly']['plugin_capabilities'] ?? []), 'Read-only should only see billing_core capabilities.');

$readonlyPlugin = $byRole['readonly']['plugin_capabilities'][0]['plugin_key'] ?? null;
assertSameValue('billing_core', $readonlyPlugin, 'Read-only plugin capability mapping mismatch.');

echo "Org management role capability map regression checks passed\n";
