<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLogService;
use App\Services\OrgManagementService;
use App\Services\RbacService;
use RuntimeException;

final class OrgManagementController
{
    public function __construct(
        private readonly OrgManagementService $orgManagement,
        private readonly RbacService $rbac,
        private readonly AuditLogService $auditLogs
    ) {
    }

    public function listCompanies(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        $actorId = $this->actorId($request);
        if ($tenantId === null || $actorId === null || !$this->authorize($request, 'org.read')) {
            return;
        }

        $includeInactive = ($request->query('include_inactive') ?? '0') === '1';
        Response::json(['data' => $this->orgManagement->listCompanies($tenantId, $actorId, $includeInactive)]);
    }

    public function upsertCompany(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        $actorId = $this->actorId($request);
        if ($tenantId === null || $actorId === null || !$this->authorize($request, 'org.manage')) {
            return;
        }

        try {
            $company = $this->orgManagement->upsertCompany($tenantId, $request->json());

            $this->auditLogs->log(
                $tenantId,
                $actorId,
                'org.company.upsert',
                'company',
                (string) ($company['company_id'] ?? 'unknown'),
                'success',
                ['payload' => $request->json()],
                $request->ipAddress(),
                $request->userAgent(),
                (string) ($company['company_id'] ?? null)
            );

            Response::json(['data' => $company], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function assignMembership(Request $request, string $companyId): void
    {
        $tenantId = $this->tenantId($request);
        $actorId = $this->actorId($request);
        if ($tenantId === null || $actorId === null || !$this->authorize($request, 'org.manage')) {
            return;
        }

        try {
            $membership = $this->orgManagement->assignMembership($tenantId, trim($companyId), $request->json());

            $this->auditLogs->log(
                $tenantId,
                $actorId,
                'org.membership.upsert',
                'company_membership',
                trim($companyId),
                'success',
                ['membership' => $membership],
                $request->ipAddress(),
                $request->userAgent(),
                trim($companyId)
            );

            Response::json(['data' => $membership], 201);
        } catch (RuntimeException $exception) {
            $status = $exception->getMessage() === 'company_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $status);
        }
    }

    public function switchContext(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        $actorId = $this->actorId($request);
        if ($tenantId === null || $actorId === null || !$this->authorize($request, 'org.read')) {
            return;
        }

        $companyId = trim((string) ($request->json()['company_id'] ?? ''));
        if ($companyId === '') {
            Response::json(['error' => 'invalid_company_id'], 422);
            return;
        }

        try {
            $context = $this->orgManagement->switchCompanyContext($tenantId, $actorId, $companyId);

            $this->auditLogs->log(
                $tenantId,
                $actorId,
                'org.context.switch',
                'company',
                $companyId,
                'success',
                ['role_key' => $context['role_key'] ?? null],
                $request->ipAddress(),
                $request->userAgent(),
                $companyId
            );

            Response::json([
                'data' => $context,
                'context_headers' => [
                    'X-Company-Id' => $context['company_id'] ?? null,
                    'X-Permissions' => implode(',', $context['permissions'] ?? []),
                ],
            ]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 403);
        }
    }

    public function listRoles(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null || !$this->authorize($request, 'rbac.manage')) {
            return;
        }

        Response::json(['data' => $this->orgManagement->listRoles($tenantId)]);
    }

    public function upsertRole(Request $request, string $roleKey): void
    {
        $tenantId = $this->tenantId($request);
        $actorId = $this->actorId($request);
        if ($tenantId === null || $actorId === null || !$this->authorize($request, 'rbac.manage')) {
            return;
        }

        try {
            $role = $this->orgManagement->upsertRole($tenantId, $roleKey, $request->json());

            $this->auditLogs->log(
                $tenantId,
                $actorId,
                'org.role.upsert',
                'role',
                (string) ($role['role_key'] ?? $roleKey),
                'success',
                ['permissions_count' => count($role['permissions'] ?? [])],
                $request->ipAddress(),
                $request->userAgent(),
                null
            );

            Response::json(['data' => $role]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function listRoleCapabilities(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null || !$this->authorize($request, 'rbac.manage')) {
            return;
        }

        Response::json(['data' => $this->orgManagement->listRoleCapabilityMap($tenantId)]);
    }

    public function listAuditLogs(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null || !$this->authorize($request, 'audit.read')) {
            return;
        }

        $companyId = trim((string) ($request->query('company_id') ?? ''));
        $limit = max(1, min(200, (int) ($request->query('limit') ?? '100')));

        $sql = 'SELECT id, tenant_id, company_id, actor_id, action_key, target_type, target_id, status, metadata_json, ip_address, user_agent, created_at
                FROM audit_logs
                WHERE tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];

        if ($companyId !== '') {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll() ?: [];
        Response::json(['data' => $rows]);
    }

    public function exportAuditLogs(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null || !$this->authorize($request, 'audit.export')) {
            return;
        }

        $companyId = trim((string) ($request->json()['company_id'] ?? ''));
        $sql = 'SELECT id, tenant_id, company_id, actor_id, action_key, target_type, target_id, status, ip_address, user_agent, created_at
                FROM audit_logs
                WHERE tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];

        if ($companyId !== '') {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $sql .= ' ORDER BY created_at DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        $csvLines = [implode(',', ['id', 'tenant_id', 'company_id', 'actor_id', 'action_key', 'target_type', 'target_id', 'status', 'ip_address', 'user_agent', 'created_at'])];
        foreach ($rows as $row) {
            $csvLines[] = implode(',', array_map([$this, 'csvEscape'], [
                (string) ($row['id'] ?? ''),
                (string) ($row['tenant_id'] ?? ''),
                (string) ($row['company_id'] ?? ''),
                (string) ($row['actor_id'] ?? ''),
                (string) ($row['action_key'] ?? ''),
                (string) ($row['target_type'] ?? ''),
                (string) ($row['target_id'] ?? ''),
                (string) ($row['status'] ?? ''),
                (string) ($row['ip_address'] ?? ''),
                (string) ($row['user_agent'] ?? ''),
                (string) ($row['created_at'] ?? ''),
            ]));
        }

        Response::json([
            'data' => [
                'format' => 'csv',
                'filename' => 'audit_logs_' . date('Ymd_His') . '.csv',
                'content' => implode("\n", $csvLines),
                'rows' => count($rows),
            ],
        ]);
    }

    private function tenantId(Request $request): ?string
    {
        $tenantId = $request->header('X-Tenant-Id');
        if (!is_string($tenantId) || trim($tenantId) === '') {
            Response::json(['error' => 'missing_tenant_header'], 422);
            return null;
        }

        return trim($tenantId);
    }

    private function actorId(Request $request): ?string
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

    private function csvEscape(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}
