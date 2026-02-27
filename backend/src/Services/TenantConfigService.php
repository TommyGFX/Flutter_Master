<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use PDO;

final class TenantConfigService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function smtpConfig(string $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT host, port, username, password, encryption, from_email, from_name FROM tenant_smtp_settings WHERE tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId]);
        $config = $stmt->fetch();

        if (is_array($config)) {
            return [
                'host' => (string) ($config['host'] ?? ''),
                'port' => (int) ($config['port'] ?? 587),
                'username' => (string) ($config['username'] ?? ''),
                'password' => (string) ($config['password'] ?? ''),
                'encryption' => (string) ($config['encryption'] ?? 'tls'),
                'from_email' => (string) ($config['from_email'] ?? ''),
                'from_name' => (string) ($config['from_name'] ?? ''),
            ];
        }

        return [
            'host' => Env::required('SMTP_HOST'),
            'port' => (int) Env::required('SMTP_PORT'),
            'username' => Env::required('SMTP_USER'),
            'password' => Env::required('SMTP_PASS'),
            'encryption' => Env::required('SMTP_ENCRYPTION'),
            'from_email' => Env::required('SMTP_FROM_EMAIL'),
            'from_name' => Env::required('SMTP_FROM_NAME'),
        ];
    }

    public function emailTemplate(string $tenantId, string $templateKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT subject, body_html FROM email_templates WHERE tenant_id = :tenant_id AND template_key = :template_key LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'template_key' => $templateKey]);

        $template = $stmt->fetch();
        if (!is_array($template)) {
            return null;
        }

        return [
            'subject' => (string) $template['subject'],
            'body_html' => (string) $template['body_html'],
        ];
    }

    public function pdfTemplate(string $tenantId, string $templateKey): ?string
    {
        $stmt = $this->pdo->prepare('SELECT body_html FROM pdf_templates WHERE tenant_id = :tenant_id AND template_key = :template_key LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'template_key' => $templateKey]);

        $template = $stmt->fetchColumn();
        if (!is_string($template) || $template === '') {
            return null;
        }

        return $template;
    }
}
