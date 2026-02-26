<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class CatalogPricingService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listProducts(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sku, type, name, description, unit_price, currency_code, tax_rate, is_active, metadata_json, updated_at
             FROM catalog_products
             WHERE tenant_id = :tenant_id
             ORDER BY name ASC, id ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(fn (array $row): array => $this->mapProduct($row), $stmt->fetchAll() ?: []);
    }

    public function saveProduct(string $tenantId, array $payload, ?int $productId = null): array
    {
        $sku = $this->normalizeSku($payload['sku'] ?? null);
        $type = $this->normalizeType($payload['type'] ?? null);
        $name = $this->normalizeName($payload['name'] ?? null);
        $description = $this->nullableString($payload['description'] ?? null);
        $unitPrice = $this->normalizeMoney($payload['unit_price'] ?? null);
        $currencyCode = $this->normalizeCurrency($payload['currency_code'] ?? 'EUR');
        $taxRate = $this->normalizeTaxRate($payload['tax_rate'] ?? 19);
        $isActive = (bool) ($payload['is_active'] ?? true);
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        if ($sku === '' || $type === '' || $name === '' || $unitPrice === null) {
            throw new RuntimeException('invalid_product_payload');
        }

        if ($productId === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO catalog_products (tenant_id, sku, type, name, description, unit_price, currency_code, tax_rate, is_active, metadata_json)
                 VALUES (:tenant_id, :sku, :type, :name, :description, :unit_price, :currency_code, :tax_rate, :is_active, :metadata_json)'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'sku' => $sku,
                'type' => $type,
                'name' => $name,
                'description' => $description,
                'unit_price' => $unitPrice,
                'currency_code' => $currencyCode,
                'tax_rate' => $taxRate,
                'is_active' => $isActive ? 1 : 0,
                'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
            ]);

            $productId = (int) $this->pdo->lastInsertId();
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE catalog_products
                 SET sku = :sku,
                     type = :type,
                     name = :name,
                     description = :description,
                     unit_price = :unit_price,
                     currency_code = :currency_code,
                     tax_rate = :tax_rate,
                     is_active = :is_active,
                     metadata_json = :metadata_json,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE tenant_id = :tenant_id AND id = :id'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'id' => $productId,
                'sku' => $sku,
                'type' => $type,
                'name' => $name,
                'description' => $description,
                'unit_price' => $unitPrice,
                'currency_code' => $currencyCode,
                'tax_rate' => $taxRate,
                'is_active' => $isActive ? 1 : 0,
                'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
            ]);

            if ($stmt->rowCount() < 1 && !$this->productExists($tenantId, $productId)) {
                throw new RuntimeException('product_not_found');
            }
        }

        return $this->getProductById($tenantId, $productId);
    }

    public function listPriceLists(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, customer_segment, currency_code, valid_from, valid_to, is_active, updated_at
             FROM catalog_price_lists
             WHERE tenant_id = :tenant_id
             ORDER BY name ASC, id ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(fn (array $row): array => $this->mapPriceList($row), $stmt->fetchAll() ?: []);
    }

    public function savePriceList(string $tenantId, array $payload, ?int $priceListId = null): array
    {
        $name = $this->normalizeName($payload['name'] ?? null);
        $customerSegment = $this->normalizeSegment($payload['customer_segment'] ?? null);
        $currencyCode = $this->normalizeCurrency($payload['currency_code'] ?? 'EUR');
        $validFrom = $this->normalizeDate($payload['valid_from'] ?? null);
        $validTo = $this->normalizeDate($payload['valid_to'] ?? null);
        $isActive = (bool) ($payload['is_active'] ?? true);

        if ($name === '') {
            throw new RuntimeException('invalid_price_list_payload');
        }

        if ($priceListId === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO catalog_price_lists (tenant_id, name, customer_segment, currency_code, valid_from, valid_to, is_active)
                 VALUES (:tenant_id, :name, :customer_segment, :currency_code, :valid_from, :valid_to, :is_active)'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'name' => $name,
                'customer_segment' => $customerSegment,
                'currency_code' => $currencyCode,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'is_active' => $isActive ? 1 : 0,
            ]);

            $priceListId = (int) $this->pdo->lastInsertId();
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE catalog_price_lists
                 SET name = :name,
                     customer_segment = :customer_segment,
                     currency_code = :currency_code,
                     valid_from = :valid_from,
                     valid_to = :valid_to,
                     is_active = :is_active,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE tenant_id = :tenant_id AND id = :id'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'id' => $priceListId,
                'name' => $name,
                'customer_segment' => $customerSegment,
                'currency_code' => $currencyCode,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'is_active' => $isActive ? 1 : 0,
            ]);

            if ($stmt->rowCount() < 1 && !$this->priceListExists($tenantId, $priceListId)) {
                throw new RuntimeException('price_list_not_found');
            }
        }

        return $this->getPriceListById($tenantId, $priceListId);
    }

    public function listPriceListItems(string $tenantId, int $priceListId): array
    {
        $this->assertPriceList($tenantId, $priceListId);

        $stmt = $this->pdo->prepare(
            'SELECT id, product_id, min_quantity, override_price, discount_percent
             FROM catalog_price_list_items
             WHERE tenant_id = :tenant_id AND price_list_id = :price_list_id
             ORDER BY product_id ASC, min_quantity ASC, id ASC'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'price_list_id' => $priceListId,
        ]);

        return array_map(function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'product_id' => (int) ($row['product_id'] ?? 0),
                'min_quantity' => (int) ($row['min_quantity'] ?? 1),
                'override_price' => (float) ($row['override_price'] ?? 0),
                'discount_percent' => (float) ($row['discount_percent'] ?? 0),
            ];
        }, $stmt->fetchAll() ?: []);
    }

    public function savePriceListItem(string $tenantId, int $priceListId, array $payload): array
    {
        $this->assertPriceList($tenantId, $priceListId);

        $productId = (int) ($payload['product_id'] ?? 0);
        if ($productId <= 0 || !$this->productExists($tenantId, $productId)) {
            throw new RuntimeException('invalid_price_list_product');
        }

        $minQuantity = max(1, (int) ($payload['min_quantity'] ?? 1));
        $overridePrice = $payload['override_price'] ?? null;
        $discountPercent = $payload['discount_percent'] ?? null;

        if ($overridePrice === null && $discountPercent === null) {
            throw new RuntimeException('price_rule_requires_override_or_discount');
        }

        $overridePriceValue = $overridePrice === null ? null : $this->normalizeMoney($overridePrice);
        $discountPercentValue = $discountPercent === null ? 0.0 : $this->normalizePercent($discountPercent);

        $stmt = $this->pdo->prepare(
            'INSERT INTO catalog_price_list_items (tenant_id, price_list_id, product_id, min_quantity, override_price, discount_percent)
             VALUES (:tenant_id, :price_list_id, :product_id, :min_quantity, :override_price, :discount_percent)
             ON DUPLICATE KEY UPDATE
                override_price = VALUES(override_price),
                discount_percent = VALUES(discount_percent),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'price_list_id' => $priceListId,
            'product_id' => $productId,
            'min_quantity' => $minQuantity,
            'override_price' => $overridePriceValue,
            'discount_percent' => $discountPercentValue,
        ]);

        return [
            'price_list_id' => $priceListId,
            'product_id' => $productId,
            'min_quantity' => $minQuantity,
            'override_price' => $overridePriceValue,
            'discount_percent' => $discountPercentValue,
        ];
    }

    public function listBundles(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT b.id, b.bundle_key, b.name, b.description, b.bundle_price, b.currency_code, b.tax_rate, b.is_active,
                    bi.product_id, bi.quantity
             FROM catalog_bundles b
             LEFT JOIN catalog_bundle_items bi
                ON bi.tenant_id = b.tenant_id
               AND bi.bundle_id = b.id
             WHERE b.tenant_id = :tenant_id
             ORDER BY b.name ASC, b.id ASC, bi.id ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        $bundles = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $bundleId = (int) ($row['id'] ?? 0);
            if (!isset($bundles[$bundleId])) {
                $bundles[$bundleId] = [
                    'id' => $bundleId,
                    'bundle_key' => (string) ($row['bundle_key'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'description' => $row['description'] ?? null,
                    'bundle_price' => (float) ($row['bundle_price'] ?? 0),
                    'currency_code' => (string) ($row['currency_code'] ?? 'EUR'),
                    'tax_rate' => (float) ($row['tax_rate'] ?? 0),
                    'is_active' => (bool) ($row['is_active'] ?? false),
                    'items' => [],
                ];
            }

            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId > 0) {
                $bundles[$bundleId]['items'][] = [
                    'product_id' => $productId,
                    'quantity' => (int) ($row['quantity'] ?? 1),
                ];
            }
        }

        return array_values($bundles);
    }

    public function saveBundle(string $tenantId, array $payload, ?int $bundleId = null): array
    {
        $bundleKey = $this->normalizeSku($payload['bundle_key'] ?? null);
        $name = $this->normalizeName($payload['name'] ?? null);
        $description = $this->nullableString($payload['description'] ?? null);
        $bundlePrice = $this->normalizeMoney($payload['bundle_price'] ?? null);
        $currencyCode = $this->normalizeCurrency($payload['currency_code'] ?? 'EUR');
        $taxRate = $this->normalizeTaxRate($payload['tax_rate'] ?? 19);
        $isActive = (bool) ($payload['is_active'] ?? true);
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        if ($bundleKey === '' || $name === '' || $bundlePrice === null || $items === []) {
            throw new RuntimeException('invalid_bundle_payload');
        }

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId <= 0 || !$this->productExists($tenantId, $productId)) {
                throw new RuntimeException('invalid_bundle_item_product');
            }
        }

        $this->pdo->beginTransaction();
        try {
            if ($bundleId === null) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO catalog_bundles (tenant_id, bundle_key, name, description, bundle_price, currency_code, tax_rate, is_active)
                     VALUES (:tenant_id, :bundle_key, :name, :description, :bundle_price, :currency_code, :tax_rate, :is_active)'
                );
                $insert->execute([
                    'tenant_id' => $tenantId,
                    'bundle_key' => $bundleKey,
                    'name' => $name,
                    'description' => $description,
                    'bundle_price' => $bundlePrice,
                    'currency_code' => $currencyCode,
                    'tax_rate' => $taxRate,
                    'is_active' => $isActive ? 1 : 0,
                ]);
                $bundleId = (int) $this->pdo->lastInsertId();
            } else {
                $update = $this->pdo->prepare(
                    'UPDATE catalog_bundles
                     SET bundle_key = :bundle_key,
                         name = :name,
                         description = :description,
                         bundle_price = :bundle_price,
                         currency_code = :currency_code,
                         tax_rate = :tax_rate,
                         is_active = :is_active,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE tenant_id = :tenant_id AND id = :id'
                );
                $update->execute([
                    'tenant_id' => $tenantId,
                    'id' => $bundleId,
                    'bundle_key' => $bundleKey,
                    'name' => $name,
                    'description' => $description,
                    'bundle_price' => $bundlePrice,
                    'currency_code' => $currencyCode,
                    'tax_rate' => $taxRate,
                    'is_active' => $isActive ? 1 : 0,
                ]);

                if ($update->rowCount() < 1 && !$this->bundleExists($tenantId, $bundleId)) {
                    throw new RuntimeException('bundle_not_found');
                }

                $deleteItems = $this->pdo->prepare('DELETE FROM catalog_bundle_items WHERE tenant_id = :tenant_id AND bundle_id = :bundle_id');
                $deleteItems->execute([
                    'tenant_id' => $tenantId,
                    'bundle_id' => $bundleId,
                ]);
            }

            $insertItem = $this->pdo->prepare(
                'INSERT INTO catalog_bundle_items (tenant_id, bundle_id, product_id, quantity)
                 VALUES (:tenant_id, :bundle_id, :product_id, :quantity)'
            );
            foreach ($items as $item) {
                $insertItem->execute([
                    'tenant_id' => $tenantId,
                    'bundle_id' => $bundleId,
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->getBundleById($tenantId, $bundleId);
    }

    public function listDiscountCodes(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT code, discount_type, discount_value, applies_to, max_redemptions, current_redemptions, valid_from, valid_to, is_active, updated_at
             FROM catalog_discount_codes
             WHERE tenant_id = :tenant_id
             ORDER BY code ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(function (array $row): array {
            return [
                'code' => (string) ($row['code'] ?? ''),
                'discount_type' => (string) ($row['discount_type'] ?? 'percent'),
                'discount_value' => (float) ($row['discount_value'] ?? 0),
                'applies_to' => (string) ($row['applies_to'] ?? 'one_time'),
                'max_redemptions' => $row['max_redemptions'] === null ? null : (int) $row['max_redemptions'],
                'current_redemptions' => (int) ($row['current_redemptions'] ?? 0),
                'valid_from' => $row['valid_from'] ?? null,
                'valid_to' => $row['valid_to'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? false),
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, $stmt->fetchAll() ?: []);
    }

    public function saveDiscountCode(string $tenantId, array $payload): array
    {
        $code = strtoupper($this->normalizeSku($payload['code'] ?? null));
        $discountType = strtolower(trim((string) ($payload['discount_type'] ?? 'percent')));
        $discountValue = $this->normalizeMoney($payload['discount_value'] ?? null);
        $appliesTo = strtolower(trim((string) ($payload['applies_to'] ?? 'one_time')));
        $maxRedemptions = isset($payload['max_redemptions']) ? max(1, (int) $payload['max_redemptions']) : null;
        $validFrom = $this->normalizeDate($payload['valid_from'] ?? null);
        $validTo = $this->normalizeDate($payload['valid_to'] ?? null);
        $isActive = (bool) ($payload['is_active'] ?? true);

        if ($code === '' || $discountValue === null || !in_array($discountType, ['percent', 'fixed'], true)) {
            throw new RuntimeException('invalid_discount_code_payload');
        }
        if (!in_array($appliesTo, ['one_time', 'subscription', 'both'], true)) {
            throw new RuntimeException('invalid_discount_applies_to');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO catalog_discount_codes (tenant_id, code, discount_type, discount_value, applies_to, max_redemptions, valid_from, valid_to, is_active)
             VALUES (:tenant_id, :code, :discount_type, :discount_value, :applies_to, :max_redemptions, :valid_from, :valid_to, :is_active)
             ON DUPLICATE KEY UPDATE
                discount_type = VALUES(discount_type),
                discount_value = VALUES(discount_value),
                applies_to = VALUES(applies_to),
                max_redemptions = VALUES(max_redemptions),
                valid_from = VALUES(valid_from),
                valid_to = VALUES(valid_to),
                is_active = VALUES(is_active),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'code' => $code,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'applies_to' => $appliesTo,
            'max_redemptions' => $maxRedemptions,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'is_active' => $isActive ? 1 : 0,
        ]);

        $select = $this->pdo->prepare(
            'SELECT code, discount_type, discount_value, applies_to, max_redemptions, current_redemptions, valid_from, valid_to, is_active
             FROM catalog_discount_codes
             WHERE tenant_id = :tenant_id AND code = :code
             LIMIT 1'
        );
        $select->execute(['tenant_id' => $tenantId, 'code' => $code]);
        $row = $select->fetch();

        if (!is_array($row)) {
            throw new RuntimeException('discount_code_not_found');
        }

        return [
            'code' => (string) ($row['code'] ?? ''),
            'discount_type' => (string) ($row['discount_type'] ?? 'percent'),
            'discount_value' => (float) ($row['discount_value'] ?? 0),
            'applies_to' => (string) ($row['applies_to'] ?? 'one_time'),
            'max_redemptions' => $row['max_redemptions'] === null ? null : (int) $row['max_redemptions'],
            'current_redemptions' => (int) ($row['current_redemptions'] ?? 0),
            'valid_from' => $row['valid_from'] ?? null,
            'valid_to' => $row['valid_to'] ?? null,
            'is_active' => (bool) ($row['is_active'] ?? false),
        ];
    }

    public function calculateQuote(string $tenantId, array $payload): array
    {
        $currency = $this->normalizeCurrency($payload['currency_code'] ?? 'EUR');
        $priceListId = isset($payload['price_list_id']) ? (int) $payload['price_list_id'] : null;
        $discountCode = isset($payload['discount_code']) ? strtoupper(trim((string) $payload['discount_code'])) : null;
        $saleType = strtolower(trim((string) ($payload['sale_type'] ?? 'one_time')));
        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];

        if ($lines === []) {
            throw new RuntimeException('invalid_quote_lines');
        }

        $priceRules = $priceListId === null ? [] : $this->loadPriceRules($tenantId, $priceListId);
        $lineResults = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $quantity = max(1, (int) ($line['quantity'] ?? 1));

            $product = $this->getProductById($tenantId, $productId);
            $baseUnitPrice = (float) ($product['unit_price'] ?? 0);
            $taxRate = (float) ($product['tax_rate'] ?? 0);

            $resolvedPrice = $this->resolvePriceWithRules($productId, $quantity, $baseUnitPrice, $priceRules);
            $lineNet = round($resolvedPrice * $quantity, 2);
            $lineTax = round($lineNet * ($taxRate / 100), 2);

            $subtotal += $lineNet;
            $taxTotal += $lineTax;

            $lineResults[] = [
                'product_id' => $productId,
                'sku' => $product['sku'] ?? null,
                'name' => $product['name'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $resolvedPrice,
                'line_net' => $lineNet,
                'tax_rate' => $taxRate,
                'line_tax' => $lineTax,
            ];
        }

        $discountAmount = 0.0;
        $discountMeta = null;
        if ($discountCode !== null && $discountCode !== '') {
            $discountMeta = $this->resolveDiscountCode($tenantId, $discountCode, $saleType, $subtotal);
            $discountAmount = (float) ($discountMeta['discount_amount'] ?? 0);
        }

        $subtotalAfterDiscount = max(0.0, round($subtotal - $discountAmount, 2));
        $taxTotalAfterDiscount = round($taxTotal * ($subtotal <= 0 ? 0 : ($subtotalAfterDiscount / $subtotal)), 2);
        $grandTotal = round($subtotalAfterDiscount + $taxTotalAfterDiscount, 2);

        return [
            'currency_code' => $currency,
            'price_list_id' => $priceListId,
            'discount' => $discountMeta,
            'lines' => $lineResults,
            'totals' => [
                'subtotal_net' => round($subtotal, 2),
                'discount_total' => round($discountAmount, 2),
                'subtotal_after_discount' => $subtotalAfterDiscount,
                'tax_total' => $taxTotalAfterDiscount,
                'grand_total' => $grandTotal,
            ],
        ];
    }

    private function resolvePriceWithRules(int $productId, int $quantity, float $basePrice, array $rules): float
    {
        $candidate = $basePrice;
        foreach ($rules as $rule) {
            if ((int) ($rule['product_id'] ?? 0) !== $productId) {
                continue;
            }

            $minQty = max(1, (int) ($rule['min_quantity'] ?? 1));
            if ($quantity < $minQty) {
                continue;
            }

            if (isset($rule['override_price']) && $rule['override_price'] !== null) {
                $candidate = (float) $rule['override_price'];
            }

            $discountPercent = (float) ($rule['discount_percent'] ?? 0);
            if ($discountPercent > 0) {
                $candidate = round($candidate * (1 - ($discountPercent / 100)), 2);
            }
        }

        return round(max(0.0, $candidate), 2);
    }

    private function resolveDiscountCode(string $tenantId, string $code, string $saleType, float $subtotal): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT code, discount_type, discount_value, applies_to, max_redemptions, current_redemptions, valid_from, valid_to, is_active
             FROM catalog_discount_codes
             WHERE tenant_id = :tenant_id AND code = :code
             LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'code' => $code]);

        $discount = $stmt->fetch();
        if (!is_array($discount) || !(bool) ($discount['is_active'] ?? false)) {
            throw new RuntimeException('discount_code_not_applicable');
        }

        $appliesTo = (string) ($discount['applies_to'] ?? 'one_time');
        if ($appliesTo !== 'both' && $appliesTo !== $saleType) {
            throw new RuntimeException('discount_code_not_applicable');
        }

        $validFrom = $discount['valid_from'] ?? null;
        $validTo = $discount['valid_to'] ?? null;
        $today = date('Y-m-d');
        if ((is_string($validFrom) && $validFrom !== '' && $today < $validFrom)
            || (is_string($validTo) && $validTo !== '' && $today > $validTo)) {
            throw new RuntimeException('discount_code_not_applicable');
        }

        $maxRedemptions = $discount['max_redemptions'] === null ? null : (int) $discount['max_redemptions'];
        $currentRedemptions = (int) ($discount['current_redemptions'] ?? 0);
        if ($maxRedemptions !== null && $currentRedemptions >= $maxRedemptions) {
            throw new RuntimeException('discount_code_exhausted');
        }

        $discountAmount = 0.0;
        $value = (float) ($discount['discount_value'] ?? 0);
        if (($discount['discount_type'] ?? 'percent') === 'fixed') {
            $discountAmount = min($subtotal, $value);
        } else {
            $discountAmount = min($subtotal, round($subtotal * ($value / 100), 2));
        }

        return [
            'code' => (string) ($discount['code'] ?? ''),
            'discount_type' => (string) ($discount['discount_type'] ?? 'percent'),
            'discount_value' => $value,
            'discount_amount' => round($discountAmount, 2),
            'applies_to' => $appliesTo,
        ];
    }

    private function loadPriceRules(string $tenantId, int $priceListId): array
    {
        $this->assertPriceList($tenantId, $priceListId);

        $stmt = $this->pdo->prepare(
            'SELECT product_id, min_quantity, override_price, discount_percent
             FROM catalog_price_list_items
             WHERE tenant_id = :tenant_id AND price_list_id = :price_list_id
             ORDER BY product_id ASC, min_quantity ASC'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'price_list_id' => $priceListId,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    private function getProductById(string $tenantId, int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sku, type, name, description, unit_price, currency_code, tax_rate, is_active, metadata_json, updated_at
             FROM catalog_products
             WHERE tenant_id = :tenant_id AND id = :id
             LIMIT 1'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $id,
        ]);

        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('product_not_found');
        }

        return $this->mapProduct($row);
    }

    private function getPriceListById(string $tenantId, int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, customer_segment, currency_code, valid_from, valid_to, is_active, updated_at
             FROM catalog_price_lists
             WHERE tenant_id = :tenant_id AND id = :id
             LIMIT 1'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $id,
        ]);

        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('price_list_not_found');
        }

        return $this->mapPriceList($row);
    }

    private function getBundleById(string $tenantId, int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM catalog_bundles WHERE tenant_id = :tenant_id AND id = :id LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $id]);
        if (!is_array($stmt->fetch())) {
            throw new RuntimeException('bundle_not_found');
        }

        foreach ($this->listBundles($tenantId) as $bundle) {
            if ((int) ($bundle['id'] ?? 0) === $id) {
                return $bundle;
            }
        }

        throw new RuntimeException('bundle_not_found');
    }

    private function mapProduct(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'sku' => (string) ($row['sku'] ?? ''),
            'type' => (string) ($row['type'] ?? 'service'),
            'name' => (string) ($row['name'] ?? ''),
            'description' => $row['description'] ?? null,
            'unit_price' => (float) ($row['unit_price'] ?? 0),
            'currency_code' => (string) ($row['currency_code'] ?? 'EUR'),
            'tax_rate' => (float) ($row['tax_rate'] ?? 0),
            'is_active' => (bool) ($row['is_active'] ?? false),
            'metadata' => $this->decodeJsonObject($row['metadata_json'] ?? null),
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function mapPriceList(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'customer_segment' => $row['customer_segment'] ?? null,
            'currency_code' => (string) ($row['currency_code'] ?? 'EUR'),
            'valid_from' => $row['valid_from'] ?? null,
            'valid_to' => $row['valid_to'] ?? null,
            'is_active' => (bool) ($row['is_active'] ?? false),
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function assertPriceList(string $tenantId, int $priceListId): void
    {
        if (!$this->priceListExists($tenantId, $priceListId)) {
            throw new RuntimeException('price_list_not_found');
        }
    }

    private function productExists(string $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM catalog_products WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $id,
        ]);

        return is_array($stmt->fetch());
    }

    private function priceListExists(string $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM catalog_price_lists WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $id,
        ]);

        return is_array($stmt->fetch());
    }

    private function bundleExists(string $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM catalog_bundles WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $id,
        ]);

        return is_array($stmt->fetch());
    }

    private function decodeJsonObject(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeSku(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    private function normalizeType(mixed $value): string
    {
        $type = strtolower(trim((string) $value));
        return in_array($type, ['product', 'service'], true) ? $type : '';
    }

    private function normalizeName(mixed $value): string
    {
        return trim((string) $value);
    }

    private function normalizeCurrency(mixed $value): string
    {
        $currency = strtoupper(trim((string) $value));
        if ($currency === '' || strlen($currency) !== 3) {
            throw new RuntimeException('invalid_currency_code');
        }

        return $currency;
    }

    private function normalizeTaxRate(mixed $value): float
    {
        $rate = (float) $value;
        if ($rate < 0 || $rate > 100) {
            throw new RuntimeException('invalid_tax_rate');
        }

        return round($rate, 4);
    }

    private function normalizeMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $money = round((float) $value, 2);
        if ($money < 0) {
            throw new RuntimeException('invalid_money_value');
        }

        return $money;
    }

    private function normalizePercent(mixed $value): float
    {
        $percent = round((float) $value, 4);
        if ($percent < 0 || $percent > 100) {
            throw new RuntimeException('invalid_percent_value');
        }

        return $percent;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $date = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('invalid_date_format');
        }

        return $date;
    }

    private function normalizeSegment(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return strtolower(trim($value));
    }


    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
