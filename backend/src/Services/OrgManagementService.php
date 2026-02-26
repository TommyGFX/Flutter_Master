<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class OrgManagementService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function listCompanies(string $tenantId, string $userId, bool $includeInactive = false): array
    {
        $sql = 'SELECT c.company_id, c.name, c.legal_name, c.tax_number, c.vat_id, c.currency_code, c.is_active, m.role_key
            FROM org_companies c
            INNER JOIN org_company_memberships m
                ON m.tenant_id = c.tenant_id
                AND m.company_id = c.company_id
            WHERE c.tenant_id = :tenant_id
                AND m.user_id = :user_id';

        if (!$includeInactive) {
            $sql .= ' AND c.is_active = 1';
        }

        $sql .= ' ORDER BY c.name ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    public function upsertCompany(string $tenantId, array $payload): array
    {
        $companyId = $this->normalizeKey($payload['company_id'] ?? null);
        if ($companyId === '') {
            throw new RuntimeException('invalid_company_id');
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('invalid_company_name');
        }

        $currencyCode = strtoupper(trim((string) ($payload['currency_code'] ?? 'EUR')));
        if (!preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new RuntimeException('invalid_currency_code');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO org_companies
                (tenant_id, company_id, name, legal_name, tax_number, vat_id, currency_code, is_active)
             VALUES
                (:tenant_id, :company_id, :name, :legal_name, :tax_number, :vat_id, :currency_code, :is_active)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                legal_name = VALUES(legal_name),
                tax_number = VALUES(tax_number),
                vat_id = VALUES(vat_id),
                currency_code = VALUES(currency_code),
                is_active = VALUES(is_active),
                updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'name' => $name,
            'legal_name' => $this->nullableString($payload['legal_name'] ?? null),
            'tax_number' => $this->nullableString($payload['tax_number'] ?? null),
            'vat_id' => $this->nullableString($payload['vat_id'] ?? null),
            'currency_code' => $currencyCode,
            'is_active' => (bool) ($payload['is_active'] ?? true) ? 1 : 0,
        ]);

        $select = $this->pdo->prepare(
            'SELECT company_id, name, legal_name, tax_number, vat_id, currency_code, is_active, updated_at
             FROM org_companies
             WHERE tenant_id = :tenant_id AND company_id = :company_id
             LIMIT 1'
        );
        $select->execute(['tenant_id' => $tenantId, 'company_id' => $companyId]);

        return $select->fetch() ?: [];
    }

    public function assignMembership(string $tenantId, string $companyId, array $payload): array
    {
        $userId = trim((string) ($payload['user_id'] ?? ''));
        $roleKey = $this->normalizeRole($payload['role_key'] ?? null);

        if ($userId === '' || $roleKey === '') {
            throw new RuntimeException('invalid_membership_payload');
        }

        $companyStmt = $this->pdo->prepare(
            'SELECT company_id FROM org_companies WHERE tenant_id = :tenant_id AND company_id = :company_id LIMIT 1'
        );
        $companyStmt->execute(['tenant_id' => $tenantId, 'company_id' => $companyId]);
        if (!$companyStmt->fetch()) {
            throw new RuntimeException('company_not_found');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO org_company_memberships (tenant_id, company_id, user_id, role_key)
             VALUES (:tenant_id, :company_id, :user_id, :role_key)
             ON DUPLICATE KEY UPDATE role_key = VALUES(role_key), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'user_id' => $userId,
            'role_key' => $roleKey,
        ]);

        return [
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'user_id' => $userId,
            'role_key' => $roleKey,
        ];
    }

    public function switchCompanyContext(string $tenantId, string $userId, string $companyId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.company_id, c.name, m.role_key
             FROM org_companies c
             INNER JOIN org_company_memberships m
                ON m.tenant_id = c.tenant_id
                AND m.company_id = c.company_id
             WHERE c.tenant_id = :tenant_id
                AND c.company_id = :company_id
                AND c.is_active = 1
                AND m.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('company_context_not_allowed');
        }

        $permissions = $this->resolveRolePermissions($tenantId, (string) ($row['role_key'] ?? ''));

        return [
            'company_id' => $row['company_id'],
            'company_name' => $row['name'],
            'role_key' => $row['role_key'],
            'permissions' => $permissions,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listRoles(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.role_key, r.name, GROUP_CONCAT(rp.permission_key) AS permissions
             FROM roles r
             LEFT JOIN role_permissions rp
                ON rp.tenant_id = r.tenant_id
                AND rp.role_key = r.role_key
             WHERE r.tenant_id = :tenant_id
             GROUP BY r.role_key, r.name
             ORDER BY r.role_key ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll() ?: [];

        return array_map(static function (array $row): array {
            $permissions = trim((string) ($row['permissions'] ?? ''));
            return [
                'role_key' => $row['role_key'],
                'name' => $row['name'],
                'permissions' => $permissions === '' ? [] : explode(',', $permissions),
            ];
        }, $rows);
    }

    public function upsertRole(string $tenantId, string $roleKeyInput, array $payload): array
    {
        $roleKey = $this->normalizeRole($roleKeyInput);
        if ($roleKey === '') {
            throw new RuntimeException('invalid_role_key');
        }

        $name = trim((string) ($payload['name'] ?? strtoupper($roleKey)));
        if ($name === '') {
            throw new RuntimeException('invalid_role_name');
        }

        $permissions = $payload['permissions'] ?? [];
        if (!is_array($permissions)) {
            throw new RuntimeException('invalid_permissions');
        }

        $permissions = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $permissions
        ))));

        $this->pdo->beginTransaction();
        try {
            $roleStmt = $this->pdo->prepare(
                'INSERT INTO roles (tenant_id, role_key, name)
                 VALUES (:tenant_id, :role_key, :name)
                 ON DUPLICATE KEY UPDATE name = VALUES(name)'
            );
            $roleStmt->execute(['tenant_id' => $tenantId, 'role_key' => $roleKey, 'name' => $name]);

            $deleteStmt = $this->pdo->prepare('DELETE FROM role_permissions WHERE tenant_id = :tenant_id AND role_key = :role_key');
            $deleteStmt->execute(['tenant_id' => $tenantId, 'role_key' => $roleKey]);

            if ($permissions !== []) {
                $insertStmt = $this->pdo->prepare(
                    'INSERT INTO role_permissions (tenant_id, role_key, permission_key)
                     VALUES (:tenant_id, :role_key, :permission_key)'
                );

                foreach ($permissions as $permission) {
                    $insertStmt->execute([
                        'tenant_id' => $tenantId,
                        'role_key' => $roleKey,
                        'permission_key' => $permission,
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'role_key' => $roleKey,
            'name' => $name,
            'permissions' => $permissions,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listRoleCapabilityMap(string $tenantId): array
    {
        $roles = $this->listRoles($tenantId);

        $stmt = $this->pdo->prepare(
            'SELECT plugin_key, display_name, lifecycle_status, is_active, capabilities_json, required_permissions_json
             FROM tenant_plugins
             WHERE tenant_id = :tenant_id
             ORDER BY display_name ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        $plugins = array_map(function (array $row): array {
            return [
                'plugin_key' => (string) ($row['plugin_key'] ?? ''),
                'display_name' => (string) ($row['display_name'] ?? ''),
                'lifecycle_status' => (string) ($row['lifecycle_status'] ?? 'installed'),
                'is_active' => (bool) ($row['is_active'] ?? false),
                'capabilities' => $this->decodeJsonList($row['capabilities_json'] ?? null),
                'required_permissions' => $this->decodeJsonList($row['required_permissions_json'] ?? null),
            ];
        }, $stmt->fetchAll() ?: []);

        return array_map(function (array $role) use ($plugins): array {
            $permissions = $role['permissions'] ?? [];
            if (!is_array($permissions)) {
                $permissions = [];
            }

            $pluginCapabilities = [];
            foreach ($plugins as $plugin) {
                $isEnabled = ($plugin['lifecycle_status'] ?? 'installed') === 'enabled' && (bool) ($plugin['is_active'] ?? false);
                if (!$isEnabled) {
                    continue;
                }

                $requiredPermissions = $plugin['required_permissions'] ?? [];
                if (!is_array($requiredPermissions) || !$this->permissionsCover($permissions, $requiredPermissions)) {
                    continue;
                }

                $pluginCapabilities[] = [
                    'plugin_key' => $plugin['plugin_key'] ?? '',
                    'display_name' => $plugin['display_name'] ?? '',
                    'capabilities' => $plugin['capabilities'] ?? [],
                ];
            }

            return [
                'role_key' => $role['role_key'] ?? '',
                'name' => $role['name'] ?? '',
                'permissions' => $permissions,
                'plugin_capabilities' => $pluginCapabilities,
            ];
        }, $roles);
    }

    /** @return array<int, string> */
    private function resolveRolePermissions(string $tenantId, string $roleKey): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT permission_key
             FROM role_permissions
             WHERE tenant_id = :tenant_id AND role_key = :role_key
             ORDER BY permission_key ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'role_key' => $roleKey]);

        return array_map(static fn (array $row): string => (string) ($row['permission_key'] ?? ''), $stmt->fetchAll() ?: []);
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeKey(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $normalized = strtolower(trim($value));
        return preg_match('/^[a-z0-9][a-z0-9_-]{1,62}$/', $normalized) ? $normalized : '';
    }

    private function normalizeRole(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $normalized = strtolower(trim($value));
        return preg_match('/^[a-z][a-z0-9_.-]{1,62}$/', $normalized) ? $normalized : '';
    }

    /** @param array<int, string> $availablePermissions @param array<int, string> $requiredPermissions */
    private function permissionsCover(array $availablePermissions, array $requiredPermissions): bool
    {
        if (in_array('*', $availablePermissions, true)) {
            return true;
        }

        foreach ($requiredPermissions as $permission) {
            if (!in_array($permission, $availablePermissions, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, string> */
    private function decodeJsonList(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $entry): string => is_string($entry) ? trim($entry) : '',
            $decoded
        )));
    }
}
