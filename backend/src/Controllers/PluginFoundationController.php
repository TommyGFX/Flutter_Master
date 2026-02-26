<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Plugin\PluginContract;
use App\Services\DomainEventService;
use App\Services\RbacService;
use InvalidArgumentException;

final class PluginFoundationController
{
    public function __construct(private readonly RbacService $rbac)
    {
    }

    public function pluginShell(Request $request): void
    {
        $tenantId = $this->resolveTenant($request);
        if ($tenantId === null || !$this->authorize($request, 'plugins.manage')) {
            return;
        }

        $pdo = Database::connection();
        $permissions = $this->permissionList($request);

        $stmt = $pdo->prepare(
            'SELECT plugin_key, display_name, version, lifecycle_status, is_active, capabilities_json, required_permissions_json
             FROM tenant_plugins
             WHERE tenant_id = :tenant_id
             ORDER BY display_name ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        $plugins = [];
        $navigation = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $requiredPermissions = $this->decodeJsonList($row['required_permissions_json'] ?? null);
            if (!$this->hasCapabilityAccess($permissions, $requiredPermissions)) {
                continue;
            }

            $lifecycleStatus = (string) ($row['lifecycle_status'] ?? 'installed');
            $isEnabled = $lifecycleStatus === 'enabled';
            $isActive = (bool) ($row['is_active'] ?? false);
            $isVisible = $isEnabled && $isActive;

            $capabilities = $this->decodeJsonList($row['capabilities_json'] ?? null);

            $plugin = [
                'plugin_key' => (string) ($row['plugin_key'] ?? ''),
                'display_name' => (string) ($row['display_name'] ?? ''),
                'version' => (string) ($row['version'] ?? '1.0.0'),
                'lifecycle_status' => $lifecycleStatus,
                'is_active' => $isActive,
                'is_visible' => $isVisible,
                'capabilities' => $capabilities,
                'required_permissions' => $requiredPermissions,
                'hooks' => PluginContract::ALLOWED_HOOKS,
            ];

            $plugins[] = $plugin;
            if ($isVisible) {
                $navigation[] = $plugin;
            }
        }

        Response::json(['data' => $plugins, 'navigation' => $navigation]);
    }

    public function setFeatureFlag(Request $request, string $flagKey): void
    {
        $tenantId = $this->resolveTenant($request);
        if ($tenantId === null || !$this->authorize($request, 'plugins.manage')) {
            return;
        }

        $companyId = $this->resolveCompanyId($request);
        $enabled = (bool) ($request->json()['enabled'] ?? false);

        $stmt = Database::connection()->prepare(
            'INSERT INTO tenant_feature_flags (tenant_id, company_id, flag_key, flag_value)
             VALUES (:tenant_id, :company_id, :flag_key, :flag_value)
             ON DUPLICATE KEY UPDATE flag_value = VALUES(flag_value), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'flag_key' => $flagKey,
            'flag_value' => $enabled ? 1 : 0,
        ]);

        Response::json(['flag_key' => $flagKey, 'enabled' => $enabled, 'company_id' => $companyId]);
    }

    public function listFeatureFlags(Request $request): void
    {
        $tenantId = $this->resolveTenant($request);
        if ($tenantId === null || !$this->authorize($request, 'plugins.manage')) {
            return;
        }

        $companyId = $this->resolveCompanyId($request);
        $stmt = Database::connection()->prepare(
            'SELECT flag_key, flag_value, updated_at
             FROM tenant_feature_flags
             WHERE tenant_id = :tenant_id AND company_id = :company_id
             ORDER BY flag_key ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'company_id' => $companyId]);

        $rows = array_map(static fn (array $row): array => [
            'flag_key' => (string) ($row['flag_key'] ?? ''),
            'enabled' => (bool) ($row['flag_value'] ?? false),
            'updated_at' => $row['updated_at'] ?? null,
        ], $stmt->fetchAll() ?: []);

        Response::json(['data' => $rows]);
    }

    public function publishDomainEvent(Request $request): void
    {
        $tenantId = $this->resolveTenant($request);
        if ($tenantId === null || !$this->authorize($request, 'plugins.manage')) {
            return;
        }

        $payload = $request->json();
        $eventName = is_string($payload['event_name'] ?? null) ? trim($payload['event_name']) : '';
        $aggregateType = is_string($payload['aggregate_type'] ?? null) ? trim($payload['aggregate_type']) : '';
        $aggregateId = is_string($payload['aggregate_id'] ?? null) ? trim($payload['aggregate_id']) : '';
        $eventPayload = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];

        if (!in_array($eventName, DomainEventService::ALLOWED_EVENTS, true)) {
            Response::json(['error' => 'unsupported_event_name'], 422);
            return;
        }

        if ($aggregateType === '' || $aggregateId === '') {
            Response::json(['error' => 'missing_aggregate_reference'], 422);
            return;
        }

        $service = new DomainEventService(Database::connection());
        $eventId = $service->publish($tenantId, $eventName, $aggregateType, $aggregateId, $eventPayload);

        Response::json(['event_id' => $eventId, 'status' => 'queued'], 201);
    }

    public function processOutbox(Request $request): void
    {
        $tenantId = $this->resolveTenant($request);
        if ($tenantId === null || !$this->authorize($request, 'plugins.manage')) {
            return;
        }

        $limit = (int) ($request->json()['limit'] ?? 50);
        $limit = max(1, min($limit, 200));

        $service = new DomainEventService(Database::connection());
        $result = $service->processOutbox($limit);
        Response::json(['tenant_id' => $tenantId, 'result' => $result]);
    }


    public function outboxMetrics(Request $request): void
    {
        $tenantId = $this->resolveTenant($request);
        if ($tenantId === null || !$this->authorize($request, 'plugins.manage')) {
            return;
        }

        $service = new DomainEventService(Database::connection());
        Response::json(['tenant_id' => $tenantId, 'metrics' => $service->outboxMetrics()]);
    }

    public function updateLifecycle(Request $request, string $pluginKey): void
    {
        $tenantId = $this->resolveTenant($request);
        if ($tenantId === null || !$this->authorize($request, 'plugins.manage')) {
            return;
        }

        $status = is_string($request->json()['lifecycle_status'] ?? null) ? trim((string) $request->json()['lifecycle_status']) : '';
        try {
            PluginContract::assertLifecycleState($status);

            $currentStatus = $this->currentLifecycleStatus($tenantId, $pluginKey);
            PluginContract::assertLifecycleTransition($currentStatus, $status);
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            $payload = ['error' => $error];
            if ($error === 'invalid_lifecycle_status') {
                $payload['allowed'] = PluginContract::LIFECYCLE_STATES;
            }

            Response::json($payload, 422);
            return;
        }

        $isActive = $status === 'enabled';
        $stmt = Database::connection()->prepare(
            'INSERT INTO tenant_plugins (tenant_id, plugin_key, display_name, lifecycle_status, is_active)
             VALUES (:tenant_id, :plugin_key, :display_name, :lifecycle_status, :is_active)
             ON DUPLICATE KEY UPDATE lifecycle_status = VALUES(lifecycle_status), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'plugin_key' => $pluginKey,
            'display_name' => $this->pluginDisplayName($pluginKey),
            'lifecycle_status' => $status,
            'is_active' => $isActive ? 1 : 0,
        ]);

        Response::json(['plugin_key' => $pluginKey, 'lifecycle_status' => $status]);
    }

    private function currentLifecycleStatus(string $tenantId, string $pluginKey): string
    {
        $stmt = Database::connection()->prepare(
            'SELECT lifecycle_status FROM tenant_plugins WHERE tenant_id = :tenant_id AND plugin_key = :plugin_key LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'plugin_key' => $pluginKey]);

        $status = $stmt->fetchColumn();
        if (!is_string($status) || $status === '') {
            return 'installed';
        }

        return $status;
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

    private function resolveCompanyId(Request $request): string
    {
        $companyId = $request->header('X-Company-Id');
        if (!is_string($companyId) || trim($companyId) === '') {
            return 'default';
        }

        return trim($companyId);
    }

    private function authorize(Request $request, string $requiredPermission): bool
    {
        $permissions = $this->permissionList($request);
        if (!$this->rbac->can($permissions, $requiredPermission)) {
            Response::json(['error' => 'forbidden', 'required_permission' => $requiredPermission], 403);
            return false;
        }

        return true;
    }

    /** @return list<string> */
    private function permissionList(Request $request): array
    {
        $rawPermissions = $request->header('X-Permissions') ?? '';
        return array_values(array_filter(array_map('trim', explode(',', $rawPermissions))));
    }

    /** @return list<string> */
    private function decodeJsonList(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => is_string($item) ? trim($item) : '',
            $decoded
        )));
    }

    private function hasCapabilityAccess(array $grantedPermissions, array $requiredPermissions): bool
    {
        if (in_array('*', $grantedPermissions, true) || $requiredPermissions === []) {
            return true;
        }

        foreach ($requiredPermissions as $requiredPermission) {
            if (!in_array($requiredPermission, $grantedPermissions, true)) {
                return false;
            }
        }

        return true;
    }

    private function pluginDisplayName(string $pluginKey): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $pluginKey));
    }
}
