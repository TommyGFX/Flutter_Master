<?php

declare(strict_types=1);

namespace App\Services;

use App\Plugin\PluginContract;
use PDO;

final class PluginManager
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function registerPluginRoute(string $tenantId, string $plugin, string $route, string $permission): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO plugin_routes (tenant_id, plugin, route, permission_key) VALUES (:tenant_id, :plugin, :route, :permission_key)');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'plugin' => $plugin,
            'route' => $route,
            'permission_key' => $permission,
        ]);
    }

    public function hooks(string $tenantId, string $hookName): array
    {
        if (!PluginContract::isAllowedHook($hookName)) {
            return [];
        }

        $stmt = $this->pdo->prepare('SELECT plugin, hook_name, config_json FROM plugin_hooks WHERE tenant_id = :tenant_id AND hook_name = :hook_name');
        $stmt->execute(['tenant_id' => $tenantId, 'hook_name' => $hookName]);
        return $stmt->fetchAll() ?: [];
    }

    public function upsertDefinition(string $pluginKey, string $version, array $capabilities, array $requiredPermissions): void
    {
        PluginContract::assertValidMetadata($pluginKey, $version, $capabilities, $requiredPermissions);

        $stmt = $this->pdo->prepare(
            'INSERT INTO plugin_definitions (plugin_key, version, display_name, capabilities_json, required_permissions_json)
             VALUES (:plugin_key, :version, :display_name, :capabilities_json, :required_permissions_json)
             ON DUPLICATE KEY UPDATE
               version = VALUES(version),
               display_name = VALUES(display_name),
               capabilities_json = VALUES(capabilities_json),
               required_permissions_json = VALUES(required_permissions_json),
               updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            'plugin_key' => $pluginKey,
            'version' => $version,
            'display_name' => ucwords(str_replace(['-', '_'], ' ', $pluginKey)),
            'capabilities_json' => json_encode(array_values($capabilities), JSON_THROW_ON_ERROR),
            'required_permissions_json' => json_encode(array_values($requiredPermissions), JSON_THROW_ON_ERROR),
        ]);
    }
}
