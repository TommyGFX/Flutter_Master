<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\BillingPaymentsService;
use RuntimeException;

final class BillingPaymentsController
{
    public function __construct(private readonly BillingPaymentsService $payments)
    {
    }

    public function createPaymentLink(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            $link = $this->payments->createPaymentLink($tenantId, (int) $documentId, $request->json());
            Response::json(['data' => $link], 201);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'document_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }

    public function listPaymentLinks(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->payments->listPaymentLinks($tenantId, (int) $documentId)]);
    }

    public function recordPayment(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            $result = $this->payments->recordPayment($tenantId, (int) $documentId, $request->json());
            Response::json(['data' => $result], 201);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'document_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }

    public function listPayments(Request $request, string $documentId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->payments->listPayments($tenantId, (int) $documentId)]);
    }

    public function saveDunningConfig(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->payments->saveDunningConfig($tenantId, $request->json())]);
    }

    public function getDunningConfig(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->payments->getDunningConfig($tenantId)]);
    }

    public function runDunning(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->payments->runDunning($tenantId)]);
    }

    public function listDunningCases(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->payments->listDunningCases($tenantId)]);
    }

    public function saveBankAccount(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->payments->saveBankAccount($tenantId, $request->json())]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function getBankAccount(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->payments->getBankAccount($tenantId)]);
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
