<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\ApprovalService;
use App\Services\AuditLogService;
use App\Services\RbacService;
use InvalidArgumentException;

final class AdminPluginController
{
    public function __construct(
        private readonly RbacService $rbac,
        private readonly ApprovalService $approvals,
        private readonly AuditLogService $auditLogs
    ) {
    }

    public function index(Request $request): void
    {
        $tenantId = $this->resolveTenant($request);
        if ($tenantId === null || !$this->authorize($request, 'plugins.manage')) {
            return;
        }

        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT plugin_key, display_name, version, lifecycle_status, capabilities_json, required_permissions_json, is_active, updated_at
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

        $actorId = $this->resolveActor($request);
        if ($actorId === null) {
            return;
        }

        $isActive = (bool) ($request->json()['is_active'] ?? false);
        $lifecycleStatus = $isActive ? 'enabled' : 'suspended';

        $approvalId = $this->approvals->createRequest(
            $tenantId,
            'plugin_status_change',
            'plugin',
            $pluginKey,
            ['is_active' => $isActive, 'lifecycle_status' => $lifecycleStatus],
            $actorId
        );

        $this->auditLogs->log(
            $tenantId,
            $actorId,
            'approval.requested',
            'plugin',
            $pluginKey,
            'pending',
            ['approval_id' => $approvalId, 'requested_state' => ['is_active' => $isActive, 'lifecycle_status' => $lifecycleStatus]],
            $request->ipAddress(),
            $request->userAgent()
        );

        Response::json([
            'status' => 'pending_approval',
            'approval_id' => $approvalId,
            'target' => ['type' => 'plugin', 'id' => $pluginKey],
        ], 202);
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

        $actorId = $this->resolveActor($request);
        if ($actorId === null) {
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

        $approvalId = $this->approvals->createRequest(
            $tenantId,
            'rbac_permission_update',
            'role',
            $roleKey,
            ['permissions' => $permissions],
            $actorId
        );

        $this->auditLogs->log(
            $tenantId,
            $actorId,
            'approval.requested',
            'role',
            $roleKey,
            'pending',
            ['approval_id' => $approvalId, 'permissions' => $permissions],
            $request->ipAddress(),
            $request->userAgent()
        );

        Response::json([
            'status' => 'pending_approval',
            'approval_id' => $approvalId,
            'target' => ['type' => 'role', 'id' => $roleKey],
        ], 202);
    }

    public function listApprovals(Request $request): void
    {
        $tenantId = $this->resolveTenant($request);
        if ($tenantId === null || !$this->authorize($request, 'approvals.manage')) {
            return;
        }

        $status = $request->header('X-Approval-Status');
        $status = is_string($status) ? trim($status) : null;

        $rows = $this->approvals->listRequests($tenantId, $status);

        $normalized = array_map(static function (array $row): array {
            $payload = [];
            if (isset($row['change_payload_json']) && is_string($row['change_payload_json'])) {
                $payload = json_decode($row['change_payload_json'], true) ?: [];
            }

            $row['change_payload'] = $payload;
            unset($row['change_payload_json']);
            return $row;
        }, $rows);

        Response::json(['data' => $normalized]);
    }

    public function approve(Request $request, string $approvalId): void
    {
        $this->decideApproval($request, $approvalId, 'approved');
    }

    public function reject(Request $request, string $approvalId): void
    {
        $this->decideApproval($request, $approvalId, 'rejected');
    }

    private function decideApproval(Request $request, string $approvalIdRaw, string $decision): void
    {
        $tenantId = $this->resolveTenant($request);
        if ($tenantId === null || !$this->authorize($request, 'approvals.manage')) {
            return;
        }

        $actorId = $this->resolveActor($request);
        if ($actorId === null) {
            return;
        }

        $approvalId = (int) $approvalIdRaw;
        if ($approvalId <= 0) {
            Response::json(['error' => 'invalid_approval_id'], 422);
            return;
        }

        $pending = $this->approvals->getPendingById($tenantId, $approvalId);
        if ($pending === null) {
            Response::json(['error' => 'approval_not_pending'], 404);
            return;
        }

        $requestedBy = (string) ($pending['requested_by'] ?? '');
        if ($requestedBy !== '' && $requestedBy === $actorId) {
            Response::json(['error' => 'self_approval_not_allowed'], 422);
            return;
        }

        $reason = $request->json()['reason'] ?? null;
        $reason = is_string($reason) ? trim($reason) : null;
        if ($reason === '') {
            $reason = null;
        }

        try {
            Database::connection()->beginTransaction();

            if ($decision === 'approved') {
                $this->applyApprovedChange($tenantId, $pending);
            }

            $this->approvals->decide($tenantId, $approvalId, $actorId, $decision, $reason);

            $targetType = (string) ($pending['target_type'] ?? 'unknown');
            $targetId = (string) ($pending['target_id'] ?? 'unknown');
            $this->auditLogs->log(
                $tenantId,
                $actorId,
                'approval.' . $decision,
                $targetType,
                $targetId,
                $decision,
                ['approval_id' => $approvalId, 'reason' => $reason],
                $request->ipAddress(),
                $request->userAgent()
            );

            Database::connection()->commit();
        } catch (InvalidArgumentException $exception) {
            if (Database::connection()->inTransaction()) {
                Database::connection()->rollBack();
            }
            Response::json(['error' => $exception->getMessage()], 422);
            return;
        } catch (\Throwable $exception) {
            if (Database::connection()->inTransaction()) {
                Database::connection()->rollBack();
            }
            throw $exception;
        }

        Response::json(['status' => $decision, 'approval_id' => $approvalId]);
    }

    private function applyApprovedChange(string $tenantId, array $pending): void
    {
        $requestType = (string) ($pending['request_type'] ?? '');
        $targetId = (string) ($pending['target_id'] ?? '');
        $payload = json_decode((string) ($pending['change_payload_json'] ?? '{}'), true) ?: [];
        $pdo = Database::connection();

        if ($requestType === 'plugin_status_change') {
            $isActive = (bool) ($payload['is_active'] ?? false);
            $lifecycleStatus = (string) ($payload['lifecycle_status'] ?? ($isActive ? 'enabled' : 'suspended'));
            $stmt = $pdo->prepare(
                'INSERT INTO tenant_plugins (tenant_id, plugin_key, display_name, version, lifecycle_status, capabilities_json, required_permissions_json, is_active)
                VALUES (:tenant_id, :plugin_key, :display_name, :version, :lifecycle_status, :capabilities_json, :required_permissions_json, :is_active)
                ON DUPLICATE KEY UPDATE
                    lifecycle_status = VALUES(lifecycle_status),
                    is_active = VALUES(is_active),
                    version = VALUES(version),
                    capabilities_json = VALUES(capabilities_json),
                    required_permissions_json = VALUES(required_permissions_json),
                    updated_at = CURRENT_TIMESTAMP'
            );

            $stmt->execute([
                'tenant_id' => $tenantId,
                'plugin_key' => $targetId,
                'display_name' => $this->pluginDisplayName($targetId),
                'version' => '1.0.0',
                'lifecycle_status' => $lifecycleStatus,
                'capabilities_json' => json_encode([], JSON_THROW_ON_ERROR),
                'required_permissions_json' => json_encode([], JSON_THROW_ON_ERROR),
                'is_active' => $isActive ? 1 : 0,
            ]);

            return;
        }

        if ($requestType === 'rbac_permission_update') {
            $permissions = $payload['permissions'] ?? [];
            if (!is_array($permissions)) {
                throw new InvalidArgumentException('invalid_permissions_payload');
            }

            $permissions = array_values(array_unique(array_filter(array_map(
                static fn (mixed $value): string => is_string($value) ? trim($value) : '',
                $permissions
            ))));

            $roleStmt = $pdo->prepare('INSERT INTO roles (tenant_id, role_key, name) VALUES (:tenant_id, :role_key, :name) ON DUPLICATE KEY UPDATE name = name');
            $roleStmt->execute([
                'tenant_id' => $tenantId,
                'role_key' => $targetId,
                'name' => strtoupper($targetId),
            ]);

            $deleteStmt = $pdo->prepare('DELETE FROM role_permissions WHERE tenant_id = :tenant_id AND role_key = :role_key');
            $deleteStmt->execute(['tenant_id' => $tenantId, 'role_key' => $targetId]);

            if ($permissions !== []) {
                $insertStmt = $pdo->prepare('INSERT INTO role_permissions (tenant_id, role_key, permission_key) VALUES (:tenant_id, :role_key, :permission_key)');
                foreach ($permissions as $permission) {
                    $insertStmt->execute([
                        'tenant_id' => $tenantId,
                        'role_key' => $targetId,
                        'permission_key' => $permission,
                    ]);
                }
            }

            return;
        }

        throw new InvalidArgumentException('unsupported_request_type');
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

    private function resolveActor(Request $request): ?string
    {
        $actorId = $request->header('X-User-Id');
        if (!is_string($actorId) || trim($actorId) === '') {
            Response::json(['error' => 'missing_user_header', 'required_header' => 'X-User-Id'], 422);
            return null;
        }

        return trim($actorId);
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
