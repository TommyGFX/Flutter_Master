<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class DocumentDeliveryService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listTemplates(string $tenantId, ?string $channel = null, ?string $locale = null): array
    {
        $query = 'SELECT id, template_key, channel, locale, subject, body_html, body_text, variables_json, attachments_json, updated_at
                  FROM document_delivery_templates
                  WHERE tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];

        if ($channel !== null && $channel !== '') {
            $query .= ' AND channel = :channel';
            $params['channel'] = $channel;
        }

        if ($locale !== null && $locale !== '') {
            $query .= ' AND locale = :locale';
            $params['locale'] = strtolower($locale);
        }

        $query .= ' ORDER BY channel ASC, locale ASC, template_key ASC';

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        return array_map(fn (array $row): array => $this->hydrateTemplate($row), $stmt->fetchAll() ?: []);
    }

    public function upsertTemplate(string $tenantId, string $templateKey, array $payload): array
    {
        $channel = strtolower(trim((string) ($payload['channel'] ?? 'email')));
        $locale = strtolower(trim((string) ($payload['locale'] ?? 'de')));
        $subject = trim((string) ($payload['subject'] ?? ''));
        $bodyHtml = (string) ($payload['body_html'] ?? '');
        $bodyText = (string) ($payload['body_text'] ?? '');

        if ($templateKey === '' || $subject === '' || $bodyHtml === '') {
            throw new RuntimeException('invalid_template_payload');
        }

        if (!in_array($channel, ['email', 'portal'], true)) {
            throw new RuntimeException('invalid_channel');
        }

        $variables = $this->normalizeStringList($payload['variables'] ?? []);
        $attachments = $this->normalizeStringList($payload['attachments'] ?? []);

        $stmt = $this->pdo->prepare(
            'INSERT INTO document_delivery_templates (tenant_id, template_key, channel, locale, subject, body_html, body_text, variables_json, attachments_json)
             VALUES (:tenant_id, :template_key, :channel, :locale, :subject, :body_html, :body_text, :variables_json, :attachments_json)
             ON DUPLICATE KEY UPDATE
                subject = VALUES(subject),
                body_html = VALUES(body_html),
                body_text = VALUES(body_text),
                variables_json = VALUES(variables_json),
                attachments_json = VALUES(attachments_json),
                updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'template_key' => $templateKey,
            'channel' => $channel,
            'locale' => $locale,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'variables_json' => json_encode($variables, JSON_THROW_ON_ERROR),
            'attachments_json' => json_encode($attachments, JSON_THROW_ON_ERROR),
        ]);

        $lookup = $this->pdo->prepare(
            'SELECT id, template_key, channel, locale, subject, body_html, body_text, variables_json, attachments_json, updated_at
             FROM document_delivery_templates
             WHERE tenant_id = :tenant_id AND template_key = :template_key AND channel = :channel AND locale = :locale
             LIMIT 1'
        );
        $lookup->execute([
            'tenant_id' => $tenantId,
            'template_key' => $templateKey,
            'channel' => $channel,
            'locale' => $locale,
        ]);

        $row = $lookup->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('template_persist_failed');
        }

        return $this->hydrateTemplate($row);
    }

    public function getProviderConfig(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT provider, from_email, from_name, reply_to, smtp_host, smtp_port, smtp_username, smtp_encryption, sendgrid_api_key, mailgun_domain, mailgun_api_key, webhook_signing_secret, updated_at
             FROM document_delivery_provider_configs
             WHERE tenant_id = :tenant_id
             LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return [
                'provider' => 'smtp',
                'from_email' => null,
                'from_name' => null,
                'reply_to' => null,
                'smtp_host' => null,
                'smtp_port' => 587,
                'smtp_username' => null,
                'smtp_encryption' => 'tls',
                'sendgrid_configured' => false,
                'mailgun_configured' => false,
                'updated_at' => null,
            ];
        }

        return [
            'provider' => (string) ($row['provider'] ?? 'smtp'),
            'from_email' => $row['from_email'] ?? null,
            'from_name' => $row['from_name'] ?? null,
            'reply_to' => $row['reply_to'] ?? null,
            'smtp_host' => $row['smtp_host'] ?? null,
            'smtp_port' => isset($row['smtp_port']) ? (int) $row['smtp_port'] : 587,
            'smtp_username' => $row['smtp_username'] ?? null,
            'smtp_encryption' => $row['smtp_encryption'] ?? 'tls',
            'sendgrid_configured' => trim((string) ($row['sendgrid_api_key'] ?? '')) !== '',
            'mailgun_configured' => trim((string) ($row['mailgun_api_key'] ?? '')) !== '' && trim((string) ($row['mailgun_domain'] ?? '')) !== '',
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    public function upsertProviderConfig(string $tenantId, array $payload): array
    {
        $provider = strtolower(trim((string) ($payload['provider'] ?? 'smtp')));
        if (!in_array($provider, ['smtp', 'sendgrid', 'mailgun'], true)) {
            throw new RuntimeException('invalid_provider');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO document_delivery_provider_configs (
                tenant_id, provider, from_email, from_name, reply_to, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption,
                sendgrid_api_key, mailgun_domain, mailgun_api_key, webhook_signing_secret
            ) VALUES (
                :tenant_id, :provider, :from_email, :from_name, :reply_to, :smtp_host, :smtp_port, :smtp_username, :smtp_password, :smtp_encryption,
                :sendgrid_api_key, :mailgun_domain, :mailgun_api_key, :webhook_signing_secret
            )
            ON DUPLICATE KEY UPDATE
                provider = VALUES(provider),
                from_email = VALUES(from_email),
                from_name = VALUES(from_name),
                reply_to = VALUES(reply_to),
                smtp_host = VALUES(smtp_host),
                smtp_port = VALUES(smtp_port),
                smtp_username = VALUES(smtp_username),
                smtp_password = VALUES(smtp_password),
                smtp_encryption = VALUES(smtp_encryption),
                sendgrid_api_key = VALUES(sendgrid_api_key),
                mailgun_domain = VALUES(mailgun_domain),
                mailgun_api_key = VALUES(mailgun_api_key),
                webhook_signing_secret = VALUES(webhook_signing_secret),
                updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'from_email' => $this->nullableString($payload['from_email'] ?? null),
            'from_name' => $this->nullableString($payload['from_name'] ?? null),
            'reply_to' => $this->nullableString($payload['reply_to'] ?? null),
            'smtp_host' => $this->nullableString($payload['smtp_host'] ?? null),
            'smtp_port' => max(1, (int) ($payload['smtp_port'] ?? 587)),
            'smtp_username' => $this->nullableString($payload['smtp_username'] ?? null),
            'smtp_password' => $this->nullableString($payload['smtp_password'] ?? null),
            'smtp_encryption' => $this->nullableString($payload['smtp_encryption'] ?? 'tls'),
            'sendgrid_api_key' => $this->nullableString($payload['sendgrid_api_key'] ?? null),
            'mailgun_domain' => $this->nullableString($payload['mailgun_domain'] ?? null),
            'mailgun_api_key' => $this->nullableString($payload['mailgun_api_key'] ?? null),
            'webhook_signing_secret' => $this->nullableString($payload['webhook_signing_secret'] ?? null),
        ]);

        return $this->getProviderConfig($tenantId);
    }

    public function listPortalDocuments(string $tenantId, int $accountId): array
    {
        $customerEmail = $this->accountEmail($tenantId, $accountId);

        $stmt = $this->pdo->prepare(
            'SELECT d.id, d.document_no, d.type, d.status, d.currency_code, d.total_gross, d.issue_date, d.due_date, d.finalized_at
             FROM billing_documents d
             INNER JOIN billing_customers c ON c.id = d.customer_id
             WHERE d.tenant_id = :tenant_id
               AND c.email = :customer_email
             ORDER BY COALESCE(d.issue_date, DATE(d.created_at)) DESC, d.id DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'customer_email' => $customerEmail]);

        return array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'document_no' => $row['document_no'] ?? null,
            'type' => (string) ($row['type'] ?? 'invoice'),
            'status' => (string) ($row['status'] ?? 'draft'),
            'currency_code' => (string) ($row['currency_code'] ?? 'EUR'),
            'total_gross' => (float) ($row['total_gross'] ?? 0),
            'issue_date' => $row['issue_date'] ?? null,
            'due_date' => $row['due_date'] ?? null,
            'finalized_at' => $row['finalized_at'] ?? null,
        ], $stmt->fetchAll() ?: []);
    }

    public function getPortalDocument(string $tenantId, int $accountId, int $documentId): array
    {
        $customerEmail = $this->accountEmail($tenantId, $accountId);

        $stmt = $this->pdo->prepare(
            'SELECT d.id, d.document_no, d.type, d.status, d.currency_code, d.total_net, d.total_tax, d.total_gross,
                    d.discount_amount, d.shipping_amount, d.issue_date, d.due_date, d.finalized_at,
                    c.email AS customer_email,
                    COALESCE(NULLIF(c.company_name, \'\'), TRIM(CONCAT(COALESCE(c.first_name, \'\'), \' \', COALESCE(c.last_name, \'\')))) AS customer_name
             FROM billing_documents d
             INNER JOIN billing_customers c ON c.id = d.customer_id
             WHERE d.tenant_id = :tenant_id AND d.id = :document_id
             LIMIT 1'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'document_id' => $documentId,
        ]);

        $document = $stmt->fetch();
        if (!is_array($document) || strtolower((string) ($document['customer_email'] ?? '')) !== strtolower($customerEmail)) {
            throw new RuntimeException('document_not_found');
        }

        $paymentStmt = $this->pdo->prepare(
            'SELECT provider, external_reference, status, amount, currency_code, expires_at, payment_url
             FROM billing_payment_links
             WHERE tenant_id = :tenant_id AND document_id = :document_id
             ORDER BY id DESC'
        );
        $paymentStmt->execute([
            'tenant_id' => $tenantId,
            'document_id' => $documentId,
        ]);

        return [
            'id' => (int) ($document['id'] ?? 0),
            'document_no' => $document['document_no'] ?? null,
            'type' => (string) ($document['type'] ?? 'invoice'),
            'status' => (string) ($document['status'] ?? 'draft'),
            'customer_name' => (string) ($document['customer_name'] ?? ''),
            'currency_code' => (string) ($document['currency_code'] ?? 'EUR'),
            'totals' => [
                'net' => (float) ($document['total_net'] ?? 0),
                'tax' => (float) ($document['total_tax'] ?? 0),
                'gross' => (float) ($document['total_gross'] ?? 0),
                'discount' => (float) ($document['discount_amount'] ?? 0),
                'shipping' => (float) ($document['shipping_amount'] ?? 0),
            ],
            'issue_date' => $document['issue_date'] ?? null,
            'due_date' => $document['due_date'] ?? null,
            'finalized_at' => $document['finalized_at'] ?? null,
            'payment_options' => array_map(static fn (array $row): array => [
                'provider' => (string) ($row['provider'] ?? 'manual'),
                'reference' => $row['external_reference'] ?? null,
                'status' => (string) ($row['status'] ?? 'created'),
                'amount' => (float) ($row['amount'] ?? 0),
                'currency_code' => (string) ($row['currency_code'] ?? 'EUR'),
                'expires_at' => $row['expires_at'] ?? null,
                'payment_url' => $row['payment_url'] ?? null,
            ], $paymentStmt->fetchAll() ?: []),
        ];
    }

    public function trackEvent(string $tenantId, array $payload): array
    {
        $eventType = strtolower(trim((string) ($payload['event_type'] ?? '')));
        if (!in_array($eventType, ['mail_open', 'link_click'], true)) {
            throw new RuntimeException('invalid_tracking_event_type');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO document_delivery_tracking_events (tenant_id, event_type, message_id, template_key, recipient, document_id, metadata_json)
             VALUES (:tenant_id, :event_type, :message_id, :template_key, :recipient, :document_id, :metadata_json)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'message_id' => $this->nullableString($payload['message_id'] ?? null),
            'template_key' => $this->nullableString($payload['template_key'] ?? null),
            'recipient' => $this->nullableString($payload['recipient'] ?? null),
            'document_id' => isset($payload['document_id']) ? (int) $payload['document_id'] : null,
            'metadata_json' => json_encode(is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [], JSON_THROW_ON_ERROR),
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'event_type' => $eventType,
            'status' => 'recorded',
        ];
    }

    private function accountEmail(string $tenantId, int $accountId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT email
             FROM tenant_accounts
             WHERE tenant_id = :tenant_id AND id = :id AND account_type = :account_type AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $accountId,
            'account_type' => 'customer',
        ]);

        $row = $stmt->fetch();
        if (!is_array($row) || trim((string) ($row['email'] ?? '')) === '') {
            throw new RuntimeException('portal_account_not_found');
        }

        return strtolower((string) $row['email']);
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }

    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $item): string {
            if (!is_scalar($item)) {
                return '';
            }

            return trim((string) $item);
        }, $value)));
    }

    private function hydrateTemplate(array $row): array
    {
        $variables = json_decode((string) ($row['variables_json'] ?? '[]'), true);
        $attachments = json_decode((string) ($row['attachments_json'] ?? '[]'), true);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'template_key' => (string) ($row['template_key'] ?? ''),
            'channel' => (string) ($row['channel'] ?? 'email'),
            'locale' => (string) ($row['locale'] ?? 'de'),
            'subject' => (string) ($row['subject'] ?? ''),
            'body_html' => (string) ($row['body_html'] ?? ''),
            'body_text' => (string) ($row['body_text'] ?? ''),
            'variables' => is_array($variables) ? array_values($variables) : [],
            'attachments' => is_array($attachments) ? array_values($attachments) : [],
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
