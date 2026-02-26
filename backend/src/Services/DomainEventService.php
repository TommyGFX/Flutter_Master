<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class DomainEventService
{
    private const MAX_RETRY_COUNT = 5;
    private const BASE_RETRY_DELAY_SECONDS = 60;
    private const MAX_RETRY_DELAY_SECONDS = 1800;
    private const STALE_PROCESSING_TIMEOUT_SECONDS = 300;

    public const ALLOWED_EVENTS = [
        'invoice.created',
        'invoice.finalized',
        'payment.received',
    ];

    /** @var null|callable(array<string,mixed>):void */
    private $dispatcher;

    public function __construct(PDO $pdo, ?callable $dispatcher = null)
    {
        $this->pdo = $pdo;
        $this->dispatcher = $dispatcher;
    }

    private readonly PDO $pdo;

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
            'SELECT id
             FROM outbox_messages
             WHERE (
                    delivery_status IN (\'pending\', \'retry\')
                    AND (next_retry_at IS NULL OR next_retry_at <= CURRENT_TIMESTAMP)
                )
                OR (
                    delivery_status = \'processing\'
                    AND updated_at < TIMESTAMPADD(SECOND, -:stale_timeout, CURRENT_TIMESTAMP)
                )
             ORDER BY id ASC
             LIMIT :limit'
        );
        $select->bindValue('stale_timeout', self::STALE_PROCESSING_TIMEOUT_SECONDS, PDO::PARAM_INT);
        $select->bindValue('limit', $limit, PDO::PARAM_INT);
        $select->execute();

        $processed = 0;
        $failed = 0;
        $skipped = 0;
        $rescheduled = 0;

        foreach ($select->fetchAll() ?: [] as $row) {
            $messageId = (int) ($row['id'] ?? 0);
            if ($messageId <= 0 || !$this->markOutboxProcessing($messageId)) {
                $skipped++;
                continue;
            }

            $message = $this->loadOutboxMessage($messageId);
            if ($message === null) {
                $skipped++;
                continue;
            }

            try {
                $this->dispatch([
                    'id' => (int) ($message['id'] ?? 0),
                    'domain_event_id' => (int) ($message['domain_event_id'] ?? 0),
                    'retry_count' => (int) ($message['retry_count'] ?? 0),
                    'payload' => is_array($message['decoded_payload'] ?? null) ? $message['decoded_payload'] : [],
                ]);
                $this->markOutboxDelivered($messageId);
                $this->markEventProcessed((int) $message['domain_event_id']);
                $processed++;
            } catch (\Throwable $exception) {
                $scheduledForRetry = $this->markOutboxRetry(
                    $messageId,
                    (int) ($message['retry_count'] ?? 0),
                    $exception->getMessage()
                );
                if ($scheduledForRetry) {
                    $rescheduled++;
                }
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
            'rescheduled' => $rescheduled,
            'skipped' => $skipped,
            'metrics' => $this->outboxMetrics(),
        ];
    }

    public function outboxMetrics(): array
    {
        $statusStmt = $this->pdo->query(
            'SELECT delivery_status, COUNT(*) AS total
             FROM outbox_messages
             GROUP BY delivery_status'
        );

        $statusCounts = [
            'pending' => 0,
            'retry' => 0,
            'processing' => 0,
            'delivered' => 0,
            'failed' => 0,
        ];
        foreach ($statusStmt->fetchAll() ?: [] as $row) {
            $status = (string) ($row['delivery_status'] ?? '');
            $statusCounts[$status] = (int) ($row['total'] ?? 0);
        }

        $oldestPendingStmt = $this->pdo->query(
            'SELECT MIN(created_at) AS oldest_pending_at
             FROM outbox_messages
             WHERE delivery_status IN (\'pending\', \'retry\', \'processing\')'
        );
        $oldestPendingAt = $oldestPendingStmt->fetchColumn();

        return [
            'status' => $statusCounts,
            'max_retry_count' => self::MAX_RETRY_COUNT,
            'stale_processing_timeout_seconds' => self::STALE_PROCESSING_TIMEOUT_SECONDS,
            'oldest_pending_at' => is_string($oldestPendingAt) ? $oldestPendingAt : null,
        ];
    }

    private function markOutboxProcessing(int $messageId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE outbox_messages
             SET delivery_status = \'processing\', updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND (
                    delivery_status IN (\'pending\', \'retry\')
                    OR (
                        delivery_status = \'processing\'
                        AND updated_at < TIMESTAMPADD(SECOND, -:stale_timeout, CURRENT_TIMESTAMP)
                    )
               )'
        );
        $stmt->bindValue('id', $messageId, PDO::PARAM_INT);
        $stmt->bindValue('stale_timeout', self::STALE_PROCESSING_TIMEOUT_SECONDS, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /** @return array<string,mixed>|null */
    private function loadOutboxMessage(int $messageId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, domain_event_id, retry_count, payload_json
             FROM outbox_messages
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $messageId]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($message)) {
            return null;
        }

        $payload = json_decode((string) ($message['payload_json'] ?? '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $message['decoded_payload'] = $payload;

        return $message;
    }

    /** @param array<string,mixed> $message */
    private function dispatch(array $message): void
    {
        if ($this->dispatcher === null) {
            return;
        }

        ($this->dispatcher)($message);
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

    private function markOutboxRetry(int $messageId, int $currentRetryCount, string $error): bool
    {
        $nextRetryCount = $currentRetryCount + 1;
        $deliveryStatus = $nextRetryCount >= self::MAX_RETRY_COUNT ? 'failed' : 'retry';

        $retryDelaySeconds = $this->retryDelaySeconds($nextRetryCount);
        $stmt = $this->pdo->prepare(
            'UPDATE outbox_messages
             SET delivery_status = :delivery_status,
                 retry_count = :retry_count,
                 last_error = :last_error,
                 next_retry_at = IF(:next_retry_at IS NULL, NULL, TIMESTAMPADD(SECOND, :next_retry_at, CURRENT_TIMESTAMP)),
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $nextRetryAt = $deliveryStatus === 'retry' ? $retryDelaySeconds : null;
        $stmt->execute([
            'id' => $messageId,
            'delivery_status' => $deliveryStatus,
            'retry_count' => $nextRetryCount,
            'last_error' => mb_substr($error, 0, 512),
            'next_retry_at' => $nextRetryAt,
        ]);

        return $deliveryStatus === 'retry';
    }

    private function retryDelaySeconds(int $retryCount): int
    {
        $exponentialDelay = self::BASE_RETRY_DELAY_SECONDS * (2 ** max(0, $retryCount - 1));

        return min(self::MAX_RETRY_DELAY_SECONDS, $exponentialDelay);
    }

    private function messageKey(string $tenantId, int $eventId, string $destination): string
    {
        return sprintf('%s:%s:%d', $tenantId, $destination, $eventId);
    }
}
