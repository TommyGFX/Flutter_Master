<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\TaxComplianceDeService;
use RuntimeException;

final class TaxComplianceDeController
{
    public function __construct(private readonly TaxComplianceDeService $taxCompliance)
    {
    }

    public function getConfig(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->taxCompliance->getConfig($tenantId)]);
    }

    public function saveConfig(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->taxCompliance->saveConfig($tenantId, $request->json())]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function preflight(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->taxCompliance->preflightDocument($tenantId, (int) $documentId)]);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'document_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }

    public function seal(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->taxCompliance->sealFinalizedDocument($tenantId, (int) $documentId)]);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'document_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }

    public function createCorrection(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->taxCompliance->createCorrectionDocument($tenantId, (int) $documentId, $request->json())], 201);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'document_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }

    public function exportEInvoice(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        $format = is_string($_GET['format'] ?? null) ? (string) $_GET['format'] : 'xrechnung';

        try {
            Response::json(['data' => $this->taxCompliance->exportEInvoice($tenantId, (int) $documentId, $format)]);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'document_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }

    public function importEInvoice(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->taxCompliance->importEInvoice($tenantId, $request->json())], 201);
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
}
