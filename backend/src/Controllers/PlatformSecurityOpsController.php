<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\PlatformSecurityOpsService;
use RuntimeException;

final class PlatformSecurityOpsController
{
    public function __construct(private readonly PlatformSecurityOpsService $securityOps)
    {
    }

    public function gdprOverview(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->securityOps->gdprOverview($tenantId)]);
    }

    public function upsertRetentionRule(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->securityOps->upsertRetentionRule($tenantId, $request->json())]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function requestDataExport(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->securityOps->requestDataExport($tenantId, $request->json())], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function requestDeletion(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->securityOps->requestDeletion($tenantId, $request->json())], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function listAuthPolicies(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->securityOps->listAuthPolicies($tenantId)]);
    }

    public function upsertAuthPolicy(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->securityOps->upsertAuthPolicy($tenantId, $request->json())]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function listBackups(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->securityOps->listBackups($tenantId)]);
    }

    public function triggerBackup(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->securityOps->triggerBackup($tenantId, $request->json())], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function restoreBackup(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->securityOps->restoreBackup($tenantId, $request->json())]);
        } catch (RuntimeException $exception) {
            $status = $exception->getMessage() === 'backup_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $status);
        }
    }

    public function listArchiveRecords(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->securityOps->listArchiveRecords($tenantId)]);
    }

    public function createArchiveRecord(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->securityOps->createArchiveRecord($tenantId, $request->json())], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function listReliabilityPolicies(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->securityOps->listReliabilityPolicies($tenantId)]);
    }

    public function upsertReliabilityPolicy(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->securityOps->upsertReliabilityPolicy($tenantId, $request->json())]);
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
