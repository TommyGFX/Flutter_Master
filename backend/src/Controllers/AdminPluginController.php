<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\RbacService;
use PDO;

final class AdminPluginController
{
    public function __construct(private readonly RbacService $rbac)
    {
    }

    public function index(Request $request): void
    {
        $tenantId = $this->resolveTenant($request);
        if ($tenantId === null || !$this->authorize($request, 'plugins.manage')) {
            return;
        }

        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT plugin_key, display_name, is_active, updated_at
            FROM tenant_plugins
            WHERE tenant_id = :tenant_id
            ORDER BY display_name ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        Response::json(['data' => $stmt->fetchAll() ?: []]);
    }

    public function setStatus(Request $request, string $pluginKey): void
    {
        $tenantId = $this->resolveTenant($request);
        if ($tenantId === null || !$this->authorize($request, 'plugins.manage')) {
            return;
        }

        $isActive = (bool) ($request->json()['is_active'] ?? false);
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'INSERT INTO tenant_plugins (tenant_id, plugin_key, display_name, is_active)
            VALUES (:tenant_id, :plugin_key, :display_name, :is_active)
            ON DUPLICATE KEY UPDATE is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'plugin_key' => $pluginKey,
            'display_name' => $this->pluginDisplayName($pluginKey),
            'is_active' => $isActive ? 1 : 0,
        ]);

        Response::json(['status' => 'ok', 'plugin_key' => $pluginKey, 'is_active' => $isActive]);
    }

    public function listRolePermissions(Request $request): void
    {
        $tenantId = $this->resolveTenant($request);
        if ($tenantId === null || !$this->authorize($request, 'rbac.manage')) {
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT role_key, permission_key
            FROM role_permissions
            WHERE tenant_id = :tenant_id
            ORDER BY role_key ASC, permission_key ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        $rows = $stmt->fetchAll() ?: [];
        $byRole = [];
        foreach ($rows as $row) {
            $roleKey = (string) ($row['role_key'] ?? '');
            $permission = (string) ($row['permission_key'] ?? '');
            if (!isset($byRole[$roleKey])) {
                $byRole[$roleKey] = [];
            }
            $byRole[$roleKey][] = $permission;
        }

        $rolesStmt = $pdo->prepare('SELECT role_key, name FROM roles WHERE tenant_id = :tenant_id ORDER BY role_key ASC');
        $rolesStmt->execute(['tenant_id' => $tenantId]);

        $roles = [];
        foreach ($rolesStmt->fetchAll() ?: [] as $role) {
            $roleKey = (string) ($role['role_key'] ?? '');
            $roles[] = [
                'role_key' => $roleKey,
                'name' => (string) ($role['name'] ?? $roleKey),
                'permissions' => $byRole[$roleKey] ?? [],
            ];
        }

        Response::json(['data' => $roles]);
    }

    public function updateRolePermissions(Request $request, string $roleKey): void
    {
        $tenantId = $this->resolveTenant($request);
        if ($tenantId === null || !$this->authorize($request, 'rbac.manage')) {
            return;
        }

        $permissions = $request->json()['permissions'] ?? [];
        if (!is_array($permissions)) {
            Response::json(['error' => 'invalid_permissions'], 422);
            return;
        }

        $permissions = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $permissions
        ))));

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $roleStmt = $pdo->prepare('INSERT INTO roles (tenant_id, role_key, name) VALUES (:tenant_id, :role_key, :name) ON DUPLICATE KEY UPDATE name = name');
            $roleStmt->execute([
                'tenant_id' => $tenantId,
                'role_key' => $roleKey,
                'name' => strtoupper($roleKey),
            ]);

            $deleteStmt = $pdo->prepare('DELETE FROM role_permissions WHERE tenant_id = :tenant_id AND role_key = :role_key');
            $deleteStmt->execute(['tenant_id' => $tenantId, 'role_key' => $roleKey]);

            if ($permissions !== []) {
                $insertStmt = $pdo->prepare('INSERT INTO role_permissions (tenant_id, role_key, permission_key) VALUES (:tenant_id, :role_key, :permission_key)');
                foreach ($permissions as $permission) {
                    $insertStmt->execute([
                        'tenant_id' => $tenantId,
                        'role_key' => $roleKey,
                        'permission_key' => $permission,
                    ]);
                }
            }

            $pdo->commit();
            Response::json(['status' => 'ok', 'role_key' => $roleKey, 'permissions' => $permissions]);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function resolveTenant(Request $request): ?string
    {
        $tenantId = $request->header('X-Tenant-Id');
        if (!is_string($tenantId) || trim($tenantId) === '') {
            Response::json(['error' => 'missing_tenant_header'], 422);
            return null;
        }

        return trim($tenantId);
    }

    private function authorize(Request $request, string $requiredPermission): bool
    {
        $rawPermissions = $request->header('X-Permissions') ?? '';
        $permissions = array_values(array_filter(array_map('trim', explode(',', $rawPermissions))));

        if (!$this->rbac->can($permissions, $requiredPermission)) {
            Response::json(['error' => 'forbidden', 'required_permission' => $requiredPermission], 403);
            return false;
        }

        return true;
    }

    private function pluginDisplayName(string $pluginKey): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $pluginKey));
    }
}
