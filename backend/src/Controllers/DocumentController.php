<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\PdfRendererService;
use App\Services\TemplateRendererService;
use App\Services\TenantConfigService;
use App\Services\TenantMailerService;
use Throwable;

final class DocumentController
{
    public function __construct(
        private readonly PdfRendererService $pdfRendererService,
        private readonly TenantMailerService $tenantMailerService,
        private readonly TemplateRendererService $templateRendererService
    ) {
    }

    public function renderPdf(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        $payload = $request->json();
        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $templateKey = is_string($payload['template_key'] ?? null) ? $payload['template_key'] : null;
        $html = is_string($payload['html'] ?? null) ? $payload['html'] : null;

        $config = new TenantConfigService(Database::connection());

        if ($templateKey !== null && $templateKey !== '') {
            $html = $config->pdfTemplate($tenantId, $templateKey);
        }

        if (!is_string($html) || $html === '') {
            Response::json(['error' => 'missing_pdf_html'], 422);
            return;
        }

        $resolvedHtml = $this->templateRendererService->render($html, $context);
        $pdfBinary = $this->pdfRendererService->render($resolvedHtml);

        Response::json([
            'filename' => is_string($payload['filename'] ?? null) ? $payload['filename'] : 'document.pdf',
            'mime' => 'application/pdf',
            'content_base64' => base64_encode($pdfBinary),
        ]);
    }

    public function sendEmail(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        $payload = $request->json();

        $to = is_string($payload['to'] ?? null) ? trim($payload['to']) : '';
        if ($to === '') {
            Response::json(['error' => 'missing_recipient'], 422);
            return;
        }

        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $subject = is_string($payload['subject'] ?? null) ? $payload['subject'] : '';
        $html = is_string($payload['html'] ?? null) ? $payload['html'] : '';
        $templateKey = is_string($payload['template_key'] ?? null) ? $payload['template_key'] : null;

        $config = new TenantConfigService(Database::connection());
        if ($templateKey !== null && $templateKey !== '') {
            $template = $config->emailTemplate($tenantId, $templateKey);
            if (is_array($template)) {
                $subject = $subject !== '' ? $subject : $template['subject'];
                $html = $html !== '' ? $html : $template['body_html'];
            }
        }

        if ($subject === '' || $html === '') {
            Response::json(['error' => 'missing_email_subject_or_body'], 422);
            return;
        }

        $renderedSubject = $this->templateRendererService->render($subject, $context);
        $renderedHtml = $this->templateRendererService->render($html, $context);

        try {
            $smtpConfig = $config->smtpConfig($tenantId);
            $this->tenantMailerService->send($to, $renderedSubject, $renderedHtml, $smtpConfig);
        } catch (Throwable $exception) {
            $error = self::classifyMailTransportFailure($exception);
            Response::json($error['body'], $error['status']);
            return;
        }

        Response::json(['sent' => true]);
    }


    public static function classifyMailTransportFailure(Throwable $exception): array
    {
        $message = trim($exception->getMessage());

        if (preg_match('/got code\s*"?([0-9]{3})"?/i', $message, $matches) === 1) {
            $smtpCode = (int) $matches[1];
            if ($smtpCode >= 500 && $smtpCode < 600) {
                return [
                    'status' => 422,
                    'body' => [
                        'error' => 'email_rejected',
                        'message' => 'Email was rejected by recipient mail server.',
                        'smtp_code' => $smtpCode,
                    ],
                ];
            }
        }

        return [
            'status' => 500,
            'body' => [
                'error' => 'email_send_failed',
                'message' => $message,
            ],
        ];
    }


    private function tenantId(Request $request): string
    {
        return $request->header('X-Tenant-Id') ?? 'default_tenant';
    }
}
