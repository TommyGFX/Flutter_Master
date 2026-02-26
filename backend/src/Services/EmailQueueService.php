<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class EmailQueueService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function enqueue(string $tenantId, string $to, string $subject, string $template, array $context): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO email_queue (tenant_id, recipient, subject, template_key, context_json, status) VALUES (:tenant_id, :recipient, :subject, :template_key, :context_json, :status)');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'recipient' => $to,
            'subject' => $subject,
            'template_key' => $template,
            'context_json' => json_encode($context, JSON_THROW_ON_ERROR),
            'status' => 'queued',
        ]);
    }
}
