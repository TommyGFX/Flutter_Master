<?php

declare(strict_types=1);

namespace App\Services;

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
        $stmt = $this->pdo->prepare('SELECT plugin, hook_name, config_json FROM plugin_hooks WHERE tenant_id = :tenant_id AND hook_name = :hook_name');
        $stmt->execute(['tenant_id' => $tenantId, 'hook_name' => $hookName]);
        return $stmt->fetchAll() ?: [];
    }
}
