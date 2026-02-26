<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

final class ApprovalService
{
    public function createRequest(
        string $tenantId,
        string $requestType,
        string $targetType,
        string $targetId,
        array $changePayload,
        string $requestedBy
    ): int {
        $stmt = Database::connection()->prepare(
            'INSERT INTO approval_requests (tenant_id, request_type, target_type, target_id, change_payload_json, requested_by)
            VALUES (:tenant_id, :request_type, :target_type, :target_id, :change_payload_json, :requested_by)'
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'request_type' => $requestType,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'change_payload_json' => json_encode($changePayload, JSON_THROW_ON_ERROR),
            'requested_by' => $requestedBy,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function listRequests(string $tenantId, ?string $status = null): array
    {
        $pdo = Database::connection();

        if ($status !== null && $status !== '') {
            $stmt = $pdo->prepare(
                'SELECT id, request_type, target_type, target_id, change_payload_json, requested_by, approved_by, status, reason, created_at, decided_at
                FROM approval_requests
                WHERE tenant_id = :tenant_id AND status = :status
                ORDER BY created_at DESC'
            );
            $stmt->execute(['tenant_id' => $tenantId, 'status' => $status]);
            return $stmt->fetchAll() ?: [];
        }

        $stmt = $pdo->prepare(
            'SELECT id, request_type, target_type, target_id, change_payload_json, requested_by, approved_by, status, reason, created_at, decided_at
            FROM approval_requests
            WHERE tenant_id = :tenant_id
            ORDER BY created_at DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll() ?: [];
    }

    public function getPendingById(string $tenantId, int $approvalId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, request_type, target_type, target_id, change_payload_json, requested_by, status
            FROM approval_requests
            WHERE tenant_id = :tenant_id AND id = :id AND status = :status
            LIMIT 1'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $approvalId,
            'status' => 'pending',
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function decide(string $tenantId, int $approvalId, string $approverId, string $decision, ?string $reason): void
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('invalid_approval_decision');
        }

        $stmt = Database::connection()->prepare(
            'UPDATE approval_requests
            SET status = :status, approved_by = :approved_by, reason = :reason, decided_at = CURRENT_TIMESTAMP
            WHERE tenant_id = :tenant_id AND id = :id AND status = :pending_status'
        );

        $stmt->execute([
            'status' => $decision,
            'approved_by' => $approverId,
            'reason' => $reason,
            'tenant_id' => $tenantId,
            'id' => $approvalId,
            'pending_status' => 'pending',
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('approval_not_pending');
        }
    }
}
