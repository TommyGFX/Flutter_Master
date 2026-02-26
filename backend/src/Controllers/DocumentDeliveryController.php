<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\DocumentDeliveryService;
use RuntimeException;

final class DocumentDeliveryController
{
    public function __construct(private readonly DocumentDeliveryService $delivery)
    {
    }

    public function listTemplates(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        $channel = $request->query('channel');
        $locale = $request->query('locale');

        Response::json([
            'data' => $this->delivery->listTemplates(
                $tenantId,
                is_string($channel) ? trim($channel) : null,
                is_string($locale) ? trim($locale) : null
            ),
        ]);
    }

    public function upsertTemplate(Request $request, string $templateKey): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            $template = $this->delivery->upsertTemplate($tenantId, trim($templateKey), $request->json());
            Response::json(['data' => $template], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function getProviderConfig(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->delivery->getProviderConfig($tenantId)]);
    }

    public function upsertProviderConfig(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            $config = $this->delivery->upsertProviderConfig($tenantId, $request->json());
            Response::json(['data' => $config]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function listPortalDocuments(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        $accountId = $this->accountIdentifier($request);
        if ($tenantId === null || $accountId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->delivery->listPortalDocuments($tenantId, $accountId)]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 404);
        }
    }

    public function getPortalDocument(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        $accountId = $this->accountIdentifier($request);
        if ($tenantId === null || $accountId === null) {
            return;
        }

        try {
            $document = $this->delivery->getPortalDocument($tenantId, $accountId, (int) $documentId);
            Response::json(['data' => $document]);
        } catch (RuntimeException $exception) {
            $status = in_array($exception->getMessage(), ['portal_account_not_found', 'document_not_found'], true) ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $status);
        }
    }

    public function trackEvent(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            $result = $this->delivery->trackEvent($tenantId, $request->json());
            Response::json(['data' => $result], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    private function tenantId(Request $request): ?string
    {
        $tenantId = $request->header('X-Tenant-Id');
        if (!is_string($tenantId) || trim($tenantId) === '') {
            Response::json(['error' => 'missing_tenant_header'], 422);
            return null;
        }

        return trim($tenantId);
    }

    public function processQueue(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        $limit = (int) ($request->query('limit') ?? 25);

        try {
            Response::json(['data' => $this->delivery->processQueue($tenantId, $limit)]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    private function accountIdentifier(Request $request): ?string
    {
        $userId = $request->header('X-User-Id');
        if (!is_string($userId) || trim($userId) === '') {
            Response::json(['error' => 'missing_or_invalid_user_header'], 422);
            return null;
        }

        return trim($userId);
    }
}
