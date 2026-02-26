<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AuditLogService
{
    public function log(
        string $tenantId,
        string $actorId,
        string $actionKey,
        string $targetType,
        string $targetId,
        string $status,
        array $metadata,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO audit_logs (tenant_id, actor_id, action_key, target_type, target_id, status, metadata_json, ip_address, user_agent)
            VALUES (:tenant_id, :actor_id, :action_key, :target_type, :target_id, :status, :metadata_json, :ip_address, :user_agent)'
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'actor_id' => $actorId,
            'action_key' => $actionKey,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'status' => $status,
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
