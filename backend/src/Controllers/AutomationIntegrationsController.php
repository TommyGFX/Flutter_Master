<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AutomationIntegrationsService;
use RuntimeException;

final class AutomationIntegrationsController
{
    public function __construct(private readonly AutomationIntegrationsService $automation)
    {
    }

    public function listApiVersions(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->automation->listApiVersions($tenantId)]);
    }

    public function registerApiVersion(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->automation->registerApiVersion($tenantId, $request->json())], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function claimIdempotency(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            $result = $this->automation->claimIdempotencyKey($tenantId, $request->json());
            Response::json(['data' => $result], ($result['is_replay'] ?? false) ? 200 : 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function listCrmConnectors(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->automation->listCrmConnectors($tenantId)]);
    }

    public function upsertCrmConnector(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->automation->upsertCrmConnector($tenantId, $request->json())]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function syncCrmEntity(Request $request, string $provider): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json([
                'data' => $this->automation->syncCrmEntity($tenantId, $provider, $request->json()),
            ], 202);
        } catch (RuntimeException $exception) {
            $status = $exception->getMessage() === 'crm_connector_not_enabled' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $status);
        }
    }

    public function listTimeEntries(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        $projectId = $request->query('project_id');
        Response::json(['data' => $this->automation->listTimeEntries($tenantId, is_string($projectId) ? $projectId : null)]);
    }

    public function upsertTimeEntry(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->automation->upsertTimeEntry($tenantId, $request->json())], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function invoiceTimeEntries(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->automation->invoiceTimeEntries($tenantId, $request->json())], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function listAutomationCatalog(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->automation->listAutomationCatalog($tenantId)]);
    }

    public function enqueueAutomationRun(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->automation->enqueueAutomationRun($tenantId, $request->json())], 202);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function processAutomationRuns(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        $limit = (int) ($request->json()['limit'] ?? 25);
        Response::json(['data' => $this->automation->processAutomationRuns($tenantId, $limit)]);
    }

    public function importPreview(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->automation->previewImport($tenantId, $request->json())]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function executeImport(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->automation->executeImport($tenantId, $request->json())], 201);
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
