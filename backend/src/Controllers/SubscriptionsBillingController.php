<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\SubscriptionsBillingService;
use RuntimeException;

final class SubscriptionsBillingController
{
    public function __construct(private readonly SubscriptionsBillingService $subscriptions)
    {
    }

    public function listPlans(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->subscriptions->listPlans($tenantId)]);
    }

    public function savePlan(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->subscriptions->savePlan($tenantId, $request->json())], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function listContracts(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->subscriptions->listContracts($tenantId)]);
    }

    public function createContract(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->subscriptions->createContract($tenantId, $request->json())], 201);
        } catch (RuntimeException $exception) {
            $code = in_array($exception->getMessage(), ['customer_not_found', 'plan_not_found'], true) ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }

    public function updateContract(Request $request, string $contractId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->subscriptions->updateContract($tenantId, (int) $contractId, $request->json())]);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'contract_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }

    public function changePlan(Request $request, string $contractId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->subscriptions->changePlan($tenantId, (int) $contractId, $request->json())]);
        } catch (RuntimeException $exception) {
            $code = in_array($exception->getMessage(), ['contract_not_found', 'plan_not_found'], true) ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }

    public function runRecurring(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            $asOf = $request->json()['as_of'] ?? null;
            Response::json(['data' => $this->subscriptions->runRecurring($tenantId, is_string($asOf) ? $asOf : null)]);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function runAutoInvoicing(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->subscriptions->runAutoInvoicing($tenantId)]);
    }

    public function runDunningRetention(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->subscriptions->runDunningRetention($tenantId)]);
    }

    public function createPaymentMethodUpdateLink(Request $request, string $contractId): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->subscriptions->createPaymentMethodUpdateLink($tenantId, (int) $contractId, $request->json())], 201);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'contract_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }

    public function completePaymentMethodUpdate(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->subscriptions->completePaymentMethodUpdate($tenantId, $request->json())]);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'payment_method_update_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
        }
    }

    public function providerWebhook(Request $request, string $provider): void
    {
        $payload = file_get_contents('php://input') ?: '{}';
        $signature = $request->header('X-Provider-Signature');

        try {
            Response::json(['data' => $this->subscriptions->handleProviderWebhook($provider, $payload, $signature)]);
        } catch (\InvalidArgumentException $exception) {
            Response::json(['error' => $exception->getMessage()], 400);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage() === 'payment_method_update_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $code);
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
