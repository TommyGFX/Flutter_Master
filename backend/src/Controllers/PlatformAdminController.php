<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLogService;
use App\Services\JwtService;
use App\Services\RefreshTokenService;

final class PlatformAdminController
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly AuditLogService $auditLogs
    ) {
    }

    public function impersonateCompany(Request $request): void
    {
        $actor = $this->requireSuperadmin($request, 'platform.impersonation');
        if ($actor === null) {
            return;
        }

        $body = $request->json();
        $tenantId = isset($body['tenant_id']) ? trim((string) $body['tenant_id']) : '';
        if ($tenantId === '') {
            Response::json(['error' => 'missing_tenant_id'], 422);
            return;
        }

        $targetUserId = isset($body['user_id']) && trim((string) $body['user_id']) !== ''
            ? trim((string) $body['user_id'])
            : 'impersonated@' . $tenantId;

        $requestedPermissions = $body['permissions'] ?? ['*'];
        if (!is_array($requestedPermissions)) {
            Response::json(['error' => 'invalid_permissions'], 422);
            return;
        }

        $permissions = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $requestedPermissions
        ))));

        if ($permissions === []) {
            $permissions = ['*'];
        }

        $claims = [
            'tenant_id' => $tenantId,
            'user_id' => $targetUserId,
            'entrypoint' => 'company',
            'permissions' => $permissions,
            'is_superadmin' => true,
            'impersonated_by' => $actor,
            'impersonation_active' => true,
        ];

        $token = $this->jwtService->issueToken($claims);
        $refreshToken = $this->refreshTokenService->issue($claims, $request->ipAddress(), $request->userAgent());

        $this->auditLogs->log(
            'superadmin',
            $actor,
            'platform.impersonation.created',
            'tenant',
            $tenantId,
            'success',
            ['impersonated_user_id' => $targetUserId, 'permissions' => $permissions],
            $request->ipAddress(),
            $request->userAgent()
        );

        Response::json([
            'token' => $token,
            'tenant_id' => $tenantId,
            'permissions' => $permissions,
            'impersonated_user_id' => $targetUserId,
            'impersonated_by' => $actor,
            ...$refreshToken,
        ]);
    }

    public function adminStats(Request $request): void
    {
        $actor = $this->requireSuperadmin($request, 'platform.stats.read');
        if ($actor === null) {
            return;
        }

        $pdo = Database::connection();

        $stats = [
            'tenants_total' => $this->countQuery($pdo, 'SELECT COUNT(*) FROM tenants'),
            'users_total' => $this->countQuery($pdo, 'SELECT COUNT(*) FROM users'),
            'active_plugins_total' => $this->countQuery($pdo, 'SELECT COUNT(*) FROM tenant_plugins WHERE is_active = 1'),
            'pending_approvals_total' => $this->countQuery($pdo, "SELECT COUNT(*) FROM approval_requests WHERE status = 'pending'"),
            'audit_logs_total' => $this->countQuery($pdo, 'SELECT COUNT(*) FROM audit_logs'),
            'active_refresh_sessions' => $this->countQuery($pdo, 'SELECT COUNT(*) FROM refresh_tokens WHERE revoked = 0 AND expires_at > NOW()'),
            'audit_logs_last_24h' => $this->countQuery($pdo, 'SELECT COUNT(*) FROM audit_logs WHERE created_at >= (NOW() - INTERVAL 1 DAY)'),
        ];

        $this->auditLogs->log(
            'superadmin',
            $actor,
            'platform.stats.viewed',
            'dashboard',
            'admin_stats',
            'success',
            ['snapshot' => $stats],
            $request->ipAddress(),
            $request->userAgent()
        );

        Response::json(['data' => $stats]);
    }

    public function globalAuditLogs(Request $request): void
    {
        $actor = $this->requireSuperadmin($request, 'platform.audit.read');
        if ($actor === null) {
            return;
        }

        $page = max(1, (int) ($request->header('X-Page') ?? 1));
        $perPage = (int) ($request->header('X-Per-Page') ?? 25);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        $tenantFilter = $request->header('X-Audit-Tenant-Id');
        $actionFilter = $request->header('X-Audit-Action');
        $statusFilter = $request->header('X-Audit-Status');

        $where = [];
        $params = [];

        if (is_string($tenantFilter) && trim($tenantFilter) !== '') {
            $where[] = 'tenant_id = :tenant_id';
            $params['tenant_id'] = trim($tenantFilter);
        }
        if (is_string($actionFilter) && trim($actionFilter) !== '') {
            $where[] = 'action_key = :action_key';
            $params['action_key'] = trim($actionFilter);
        }
        if (is_string($statusFilter) && trim($statusFilter) !== '') {
            $where[] = 'status = :status';
            $params['status'] = trim($statusFilter);
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $pdo = Database::connection();

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM audit_logs' . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            'SELECT id, tenant_id, actor_id, action_key, target_type, target_id, status, metadata_json, ip_address, user_agent, created_at
            FROM audit_logs' . $whereSql . '
            ORDER BY created_at DESC, id DESC
            LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(static function (array $row): array {
            $metadata = json_decode((string) ($row['metadata_json'] ?? '{}'), true);

            return [
                'id' => (int) ($row['id'] ?? 0),
                'tenant_id' => (string) ($row['tenant_id'] ?? ''),
                'actor_id' => (string) ($row['actor_id'] ?? ''),
                'action_key' => (string) ($row['action_key'] ?? ''),
                'target_type' => (string) ($row['target_type'] ?? ''),
                'target_id' => (string) ($row['target_id'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'metadata' => is_array($metadata) ? $metadata : [],
                'ip_address' => $row['ip_address'] ?? null,
                'user_agent' => $row['user_agent'] ?? null,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }, $stmt->fetchAll() ?: []);

        Response::json([
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    public function platformReports(Request $request): void
    {
        $actor = $this->requireSuperadmin($request, 'platform.reports.read');
        if ($actor === null) {
            return;
        }

        $pdo = Database::connection();

        $stmt = $pdo->query(
            "SELECT
                t.id AS tenant_id,
                t.name,
                COALESCE(u.users_total, 0) AS users_total,
                COALESCE(p.plugins_active, 0) AS plugins_active,
                COALESCE(ar.approvals_pending, 0) AS approvals_pending,
                COALESCE(al.audit_30d, 0) AS audit_30d,
                COALESCE(rs.sessions_active, 0) AS sessions_active
            FROM tenants t
            LEFT JOIN (
                SELECT tenant_id, COUNT(*) AS users_total
                FROM users
                GROUP BY tenant_id
            ) u ON u.tenant_id = t.id
            LEFT JOIN (
                SELECT tenant_id, COUNT(*) AS plugins_active
                FROM tenant_plugins
                WHERE is_active = 1
                GROUP BY tenant_id
            ) p ON p.tenant_id = t.id
            LEFT JOIN (
                SELECT tenant_id, COUNT(*) AS approvals_pending
                FROM approval_requests
                WHERE status = 'pending'
                GROUP BY tenant_id
            ) ar ON ar.tenant_id = t.id
            LEFT JOIN (
                SELECT tenant_id, COUNT(*) AS audit_30d
                FROM audit_logs
                WHERE created_at >= (NOW() - INTERVAL 30 DAY)
                GROUP BY tenant_id
            ) al ON al.tenant_id = t.id
            LEFT JOIN (
                SELECT tenant_id, COUNT(*) AS sessions_active
                FROM refresh_tokens
                WHERE revoked = 0 AND expires_at > NOW()
                GROUP BY tenant_id
            ) rs ON rs.tenant_id = t.id
            ORDER BY t.created_at DESC"
        );

        $tenants = array_map(static function (array $row): array {
            return [
                'tenant_id' => (string) ($row['tenant_id'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'users_total' => (int) ($row['users_total'] ?? 0),
                'plugins_active' => (int) ($row['plugins_active'] ?? 0),
                'approvals_pending' => (int) ($row['approvals_pending'] ?? 0),
                'audit_30d' => (int) ($row['audit_30d'] ?? 0),
                'sessions_active' => (int) ($row['sessions_active'] ?? 0),
            ];
        }, $stmt->fetchAll() ?: []);

        $summary = [
            'tenants_total' => count($tenants),
            'users_total' => array_sum(array_column($tenants, 'users_total')),
            'plugins_active_total' => array_sum(array_column($tenants, 'plugins_active')),
            'approvals_pending_total' => array_sum(array_column($tenants, 'approvals_pending')),
            'audit_30d_total' => array_sum(array_column($tenants, 'audit_30d')),
            'sessions_active_total' => array_sum(array_column($tenants, 'sessions_active')),
        ];

        $this->auditLogs->log(
            'superadmin',
            $actor,
            'platform.reports.viewed',
            'analytics',
            'platform_reports',
            'success',
            ['summary' => $summary],
            $request->ipAddress(),
            $request->userAgent()
        );

        Response::json([
            'summary' => $summary,
            'tenants' => $tenants,
        ]);
    }

    private function requireSuperadmin(Request $request, string $requiredAction): ?string
    {
        $token = $this->bearerToken($request);
        if ($token === null) {
            Response::json(['error' => 'missing_bearer_token'], 401);
            return null;
        }

        $claims = $this->jwtService->verify($token);
        if (!is_array($claims)) {
            Response::json(['error' => 'invalid_token'], 401);
            return null;
        }

        if (($claims['is_superadmin'] ?? false) !== true) {
            Response::json(['error' => 'forbidden', 'required' => 'superadmin'], 403);
            return null;
        }

        $permissions = $claims['permissions'] ?? [];
        $permissions = is_array($permissions) ? $permissions : [];
        if (!in_array('*', $permissions, true) && !in_array($requiredAction, $permissions, true)) {
            Response::json(['error' => 'forbidden', 'required_permission' => $requiredAction], 403);
            return null;
        }

        $actor = isset($claims['user_id']) ? trim((string) $claims['user_id']) : '';
        if ($actor === '') {
            Response::json(['error' => 'invalid_token_actor'], 401);
            return null;
        }

        return $actor;
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if (!is_string($header)) {
            return null;
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
            return null;
        }

        return trim((string) ($matches[1] ?? ''));
    }

    private function countQuery(\PDO $pdo, string $query): int
    {
        $stmt = $pdo->query($query);
        return (int) ($stmt->fetchColumn() ?: 0);
    }
}
