<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\FinanceReportingService;
use RuntimeException;

final class FinanceReportingController
{
    public function __construct(private readonly FinanceReportingService $reporting)
    {
    }

    public function kpis(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json([
                'data' => $this->reporting->kpiDashboard(
                    $tenantId,
                    $request->query('from'),
                    $request->query('to')
                ),
            ]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function openItems(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->reporting->openItems($tenantId, $request->query('as_of'))]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function taxReport(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json([
                'data' => $this->reporting->taxReport(
                    $tenantId,
                    $request->query('from'),
                    $request->query('to')
                ),
            ]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function export(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        $payload = $request->json();
        $type = is_string($payload['type'] ?? null) ? $payload['type'] : '';
        $format = is_string($payload['format'] ?? null) ? $payload['format'] : 'csv';
        $fromDate = is_string($payload['from'] ?? null) ? $payload['from'] : null;
        $toDate = is_string($payload['to'] ?? null) ? $payload['to'] : null;

        try {
            Response::json([
                'data' => $this->reporting->export($tenantId, $type, $format, $fromDate, $toDate),
            ]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function listConnectors(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->reporting->listConnectors($tenantId)]);
    }

    public function upsertConnector(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->reporting->upsertConnector($tenantId, $request->json())]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function publishWebhook(Request $request, string $provider): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json([
                'data' => $this->reporting->publishWebhook($tenantId, $provider, $request->json()),
            ], 202);
        } catch (RuntimeException $exception) {
            $statusCode = $exception->getMessage() === 'connector_not_enabled' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $statusCode);
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
