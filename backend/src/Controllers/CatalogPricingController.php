<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogPricingService;
use RuntimeException;

final class CatalogPricingController
{
    public function __construct(private readonly CatalogPricingService $catalog)
    {
    }

    public function listProducts(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->catalog->listProducts($tenantId)]);
    }

    public function saveProduct(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->catalog->saveProduct($tenantId, $request->json())], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function updateProduct(Request $request, string $id): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->catalog->saveProduct($tenantId, $request->json(), (int) $id)]);
        } catch (RuntimeException $exception) {
            $status = $exception->getMessage() === 'product_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $status);
        }
    }

    public function listPriceLists(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->catalog->listPriceLists($tenantId)]);
    }

    public function savePriceList(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->catalog->savePriceList($tenantId, $request->json())], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function updatePriceList(Request $request, string $id): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->catalog->savePriceList($tenantId, $request->json(), (int) $id)]);
        } catch (RuntimeException $exception) {
            $status = $exception->getMessage() === 'price_list_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $status);
        }
    }

    public function listPriceListItems(Request $request, string $id): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->catalog->listPriceListItems($tenantId, (int) $id)]);
        } catch (RuntimeException $exception) {
            $status = $exception->getMessage() === 'price_list_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $status);
        }
    }

    public function savePriceListItem(Request $request, string $id): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->catalog->savePriceListItem($tenantId, (int) $id, $request->json())], 201);
        } catch (RuntimeException $exception) {
            $status = in_array($exception->getMessage(), ['price_list_not_found', 'product_not_found'], true) ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $status);
        }
    }

    public function listBundles(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->catalog->listBundles($tenantId)]);
    }

    public function saveBundle(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->catalog->saveBundle($tenantId, $request->json())], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function updateBundle(Request $request, string $id): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->catalog->saveBundle($tenantId, $request->json(), (int) $id)]);
        } catch (RuntimeException $exception) {
            $status = $exception->getMessage() === 'bundle_not_found' ? 404 : 422;
            Response::json(['error' => $exception->getMessage()], $status);
        }
    }

    public function listDiscountCodes(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        Response::json(['data' => $this->catalog->listDiscountCodes($tenantId)]);
    }

    public function saveDiscountCode(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->catalog->saveDiscountCode($tenantId, $request->json())], 201);
        } catch (RuntimeException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function calculateQuote(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        try {
            Response::json(['data' => $this->catalog->calculateQuote($tenantId, $request->json())]);
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
