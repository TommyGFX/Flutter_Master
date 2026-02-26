<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class DomainEventService
{
    public const ALLOWED_EVENTS = [
        'invoice.created',
        'invoice.finalized',
        'payment.received',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function publish(
        string $tenantId,
        string $eventName,
        string $aggregateType,
        string $aggregateId,
        array $payload,
        string $destination = 'internal'
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO domain_events (tenant_id, event_name, aggregate_type, aggregate_id, payload_json)
             VALUES (:tenant_id, :event_name, :aggregate_type, :aggregate_id, :payload_json)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'event_name' => $eventName,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        $eventId = (int) $this->pdo->lastInsertId();

        $outboxStmt = $this->pdo->prepare(
            'INSERT INTO outbox_messages (tenant_id, domain_event_id, destination, message_key, payload_json)
             VALUES (:tenant_id, :domain_event_id, :destination, :message_key, :payload_json)'
        );
        $outboxStmt->execute([
            'tenant_id' => $tenantId,
            'domain_event_id' => $eventId,
            'destination' => $destination,
            'message_key' => $this->messageKey($tenantId, $eventId, $destination),
            'payload_json' => json_encode([
                'event_name' => $eventName,
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR),
        ]);

        return $eventId;
    }

    public function processOutbox(int $limit = 50): array
    {
        $select = $this->pdo->prepare(
            'SELECT id, domain_event_id
             FROM outbox_messages
             WHERE delivery_status IN (\'pending\', \'retry\')
               AND (next_retry_at IS NULL OR next_retry_at <= CURRENT_TIMESTAMP)
             ORDER BY id ASC
             LIMIT :limit'
        );
        $select->bindValue('limit', $limit, PDO::PARAM_INT);
        $select->execute();

        $processed = 0;
        $failed = 0;

        foreach ($select->fetchAll() ?: [] as $row) {
            $messageId = (int) ($row['id'] ?? 0);
            $eventId = (int) ($row['domain_event_id'] ?? 0);

            try {
                $this->markOutboxDelivered($messageId);
                $this->markEventProcessed($eventId);
                $processed++;
            } catch (\Throwable $exception) {
                $this->markOutboxRetry($messageId, $exception->getMessage());
                $failed++;
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    private function markOutboxDelivered(int $messageId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE outbox_messages
             SET delivery_status = \'delivered\', processed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute(['id' => $messageId]);
    }

    private function markEventProcessed(int $eventId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE domain_events
             SET event_status = \'processed\', processed_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute(['id' => $eventId]);
    }

    private function markOutboxRetry(int $messageId, string $error): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE outbox_messages
             SET delivery_status = \'retry\',
                 retry_count = retry_count + 1,
                 last_error = :last_error,
                 next_retry_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 5 MINUTE),
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $messageId,
            'last_error' => mb_substr($error, 0, 512),
        ]);
    }

    private function messageKey(string $tenantId, int $eventId, string $destination): string
    {
        return sprintf('%s:%s:%d', $tenantId, $destination, $eventId);
    }
}
