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

$service = new OrgManagementService($pdo);
$roles = $service->listRoles('tenant_seeded');

assertSameValue(4, count($roles), 'Expected exactly four seeded default profiles.');

$byRole = [];
foreach ($roles as $role) {
    $byRole[(string) ($role['role_key'] ?? '')] = $role;
}

assertSameValue(['*'], $byRole['admin']['permissions'] ?? [], 'Admin defaults should grant wildcard permission.');
assertSameValue(true, isset($byRole['buchhaltung']), 'Buchhaltung default profile missing.');
assertSameValue(true, isset($byRole['vertrieb']), 'Vertrieb default profile missing.');
assertSameValue(true, isset($byRole['readonly']), 'Read-only default profile missing.');

echo "Org management default role seed regression checks passed\n";
