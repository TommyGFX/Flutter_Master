<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class BillingCoreService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listDocuments(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, document_type, document_number, status, customer_id, customer_name_snapshot, currency_code, grand_total, created_at, finalized_at, due_date
             FROM billing_documents
             WHERE tenant_id = :tenant_id
             ORDER BY created_at DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll() ?: [];
    }

    public function getDocument(string $tenantId, int $documentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM billing_documents WHERE tenant_id = :tenant_id AND id = :id LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $documentId]);
        $document = $stmt->fetch();

        if (!is_array($document)) {
            return null;
        }

        $lineItemsStmt = $this->pdo->prepare(
            'SELECT * FROM billing_line_items WHERE tenant_id = :tenant_id AND document_id = :document_id ORDER BY id ASC'
        );
        $lineItemsStmt->execute(['tenant_id' => $tenantId, 'document_id' => $documentId]);

        $taxStmt = $this->pdo->prepare(
            'SELECT tax_rate, tax_amount, net_amount, gross_amount
             FROM billing_tax_breakdowns
             WHERE tenant_id = :tenant_id AND document_id = :document_id
             ORDER BY tax_rate ASC'
        );
        $taxStmt->execute(['tenant_id' => $tenantId, 'document_id' => $documentId]);

        $addressStmt = $this->pdo->prepare(
            'SELECT address_type, company_name, first_name, last_name, street, house_number, postal_code, city, country, email, phone
             FROM billing_document_addresses
             WHERE tenant_id = :tenant_id AND document_id = :document_id'
        );
        $addressStmt->execute(['tenant_id' => $tenantId, 'document_id' => $documentId]);

        $document['line_items'] = $lineItemsStmt->fetchAll() ?: [];
        $document['tax_breakdown'] = $taxStmt->fetchAll() ?: [];
        $document['addresses'] = $addressStmt->fetchAll() ?: [];
        $document['totals'] = json_decode((string) ($document['totals_json'] ?? '{}'), true) ?: [];

        return $document;
    }

    public function saveDraft(string $tenantId, array $payload, ?int $documentId = null): int
    {
        $this->pdo->beginTransaction();

        try {
            $customerId = isset($payload['customer_id']) ? (int) $payload['customer_id'] : null;
            $customer = $customerId !== null ? $this->findCustomer($tenantId, $customerId) : null;
            if ($customerId !== null && $customer === null) {
                throw new RuntimeException('unknown_customer');
            }

            $documentType = $this->normalizeDocumentType((string) ($payload['document_type'] ?? 'invoice'));
            $currencyCode = strtoupper((string) ($payload['currency_code'] ?? 'EUR'));
            $exchangeRate = $this->normalizeDecimal($payload['exchange_rate'] ?? 1.0, 6);
            $lineItems = is_array($payload['line_items'] ?? null) ? $payload['line_items'] : [];
            $discountTotal = $this->normalizeDecimal($payload['discount_total'] ?? 0.0);
            $shippingTotal = $this->normalizeDecimal($payload['shipping_total'] ?? 0.0);
            $feesTotal = $this->normalizeDecimal($payload['fees_total'] ?? 0.0);
            $totals = $this->calculateTotals($lineItems, $discountTotal, $shippingTotal, $feesTotal);
            $dueDate = isset($payload['due_date']) && is_string($payload['due_date']) ? $payload['due_date'] : null;
            $referenceDocumentId = isset($payload['reference_document_id']) ? (int) $payload['reference_document_id'] : null;

            if ($documentId === null) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO billing_documents
                        (tenant_id, plugin_key, document_type, status, customer_id, customer_name_snapshot, currency_code, exchange_rate,
                         subtotal_net, discount_total, shipping_total, fees_total, tax_total, grand_total, totals_json, due_date, reference_document_id)
                     VALUES
                        (:tenant_id, :plugin_key, :document_type, :status, :customer_id, :customer_name_snapshot, :currency_code, :exchange_rate,
                         :subtotal_net, :discount_total, :shipping_total, :fees_total, :tax_total, :grand_total, :totals_json, :due_date, :reference_document_id)'
                );
                $insert->execute([
                    'tenant_id' => $tenantId,
                    'plugin_key' => 'billing_core',
                    'document_type' => $documentType,
                    'status' => 'draft',
                    'customer_id' => $customerId,
                    'customer_name_snapshot' => $this->customerNameSnapshot($customer),
                    'currency_code' => $currencyCode,
                    'exchange_rate' => $exchangeRate,
                    'subtotal_net' => $totals['subtotal_net'],
                    'discount_total' => $discountTotal,
                    'shipping_total' => $shippingTotal,
                    'fees_total' => $feesTotal,
                    'tax_total' => $totals['tax_total'],
                    'grand_total' => $totals['grand_total'],
                    'totals_json' => json_encode($totals, JSON_THROW_ON_ERROR),
                    'due_date' => $dueDate,
                    'reference_document_id' => $referenceDocumentId,
                ]);
                $documentId = (int) $this->pdo->lastInsertId();
            } else {
                $this->assertMutable($tenantId, $documentId);

                $update = $this->pdo->prepare(
                    'UPDATE billing_documents
                     SET document_type = :document_type,
                         customer_id = :customer_id,
                         customer_name_snapshot = :customer_name_snapshot,
                         currency_code = :currency_code,
                         exchange_rate = :exchange_rate,
                         subtotal_net = :subtotal_net,
                         discount_total = :discount_total,
                         shipping_total = :shipping_total,
                         fees_total = :fees_total,
                         tax_total = :tax_total,
                         grand_total = :grand_total,
                         totals_json = :totals_json,
                         due_date = :due_date,
                         reference_document_id = :reference_document_id,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE tenant_id = :tenant_id AND id = :id'
                );
                $update->execute([
                    'tenant_id' => $tenantId,
                    'id' => $documentId,
                    'document_type' => $documentType,
                    'customer_id' => $customerId,
                    'customer_name_snapshot' => $this->customerNameSnapshot($customer),
                    'currency_code' => $currencyCode,
                    'exchange_rate' => $exchangeRate,
                    'subtotal_net' => $totals['subtotal_net'],
                    'discount_total' => $discountTotal,
                    'shipping_total' => $shippingTotal,
                    'fees_total' => $feesTotal,
                    'tax_total' => $totals['tax_total'],
                    'grand_total' => $totals['grand_total'],
                    'totals_json' => json_encode($totals, JSON_THROW_ON_ERROR),
                    'due_date' => $dueDate,
                    'reference_document_id' => $referenceDocumentId,
                ]);

                $this->pdo->prepare('DELETE FROM billing_line_items WHERE tenant_id = :tenant_id AND document_id = :document_id')->execute([
                    'tenant_id' => $tenantId,
                    'document_id' => $documentId,
                ]);
                $this->pdo->prepare('DELETE FROM billing_tax_breakdowns WHERE tenant_id = :tenant_id AND document_id = :document_id')->execute([
                    'tenant_id' => $tenantId,
                    'document_id' => $documentId,
                ]);
                $this->pdo->prepare('DELETE FROM billing_document_addresses WHERE tenant_id = :tenant_id AND document_id = :document_id')->execute([
                    'tenant_id' => $tenantId,
                    'document_id' => $documentId,
                ]);
            }

            $this->storeLineItems($tenantId, $documentId, $lineItems);
            $this->storeTaxBreakdown($tenantId, $documentId, $totals['tax_breakdown']);
            $this->storeAddresses($tenantId, $documentId, $payload['addresses'] ?? [], $customer);
            $this->appendHistory($tenantId, $documentId, 'document.saved', ['status' => 'draft', 'document_type' => $documentType]);

            $this->pdo->commit();
            return $documentId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function finalizeDocument(string $tenantId, int $documentId): array
    {
        $this->pdo->beginTransaction();

        try {
            $document = $this->getDocumentForUpdate($tenantId, $documentId);
            if ($document === null) {
                throw new RuntimeException('document_not_found');
            }
            if (($document['status'] ?? '') !== 'draft') {
                throw new RuntimeException('document_not_mutable');
            }

            $series = strtoupper((string) ($document['document_type'] ?? 'INV'));
            $year = (int) date('Y');
            $nextNumber = $this->reserveNumber($tenantId, $series, $year);
            $documentNumber = sprintf('%s-%d-%05d', $series, $year, $nextNumber);

            $update = $this->pdo->prepare(
                'UPDATE billing_documents
                 SET document_number = :document_number, status = :status, finalized_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                 WHERE tenant_id = :tenant_id AND id = :id'
            );
            $update->execute([
                'tenant_id' => $tenantId,
                'id' => $documentId,
                'document_number' => $documentNumber,
                'status' => 'sent',
            ]);

            $this->appendHistory($tenantId, $documentId, 'document.finalized', ['document_number' => $documentNumber]);

            $this->pdo->commit();
            return ['document_id' => $documentId, 'document_number' => $documentNumber, 'status' => 'sent'];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function setStatus(string $tenantId, int $documentId, string $status): void
    {
        $allowed = ['draft', 'sent', 'due', 'paid', 'overdue'];
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('invalid_status');
        }

        $stmt = $this->pdo->prepare('UPDATE billing_documents SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant_id AND id = :id');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $documentId, 'status' => $status]);

        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('document_not_found');
        }

        $this->appendHistory($tenantId, $documentId, 'document.status_changed', ['status' => $status]);
    }

    public function convertQuoteToInvoice(string $tenantId, int $sourceDocumentId): int
    {
        $source = $this->getDocument($tenantId, $sourceDocumentId);
        if ($source === null) {
            throw new RuntimeException('document_not_found');
        }
        if (($source['document_type'] ?? '') !== 'quote') {
            throw new RuntimeException('conversion_requires_quote');
        }

        $payload = [
            'document_type' => 'invoice',
            'customer_id' => $source['customer_id'] !== null ? (int) $source['customer_id'] : null,
            'currency_code' => $source['currency_code'] ?? 'EUR',
            'exchange_rate' => $source['exchange_rate'] ?? 1,
            'discount_total' => $source['discount_total'] ?? 0,
            'shipping_total' => $source['shipping_total'] ?? 0,
            'fees_total' => $source['fees_total'] ?? 0,
            'due_date' => $source['due_date'] ?? null,
            'reference_document_id' => $sourceDocumentId,
            'line_items' => $source['line_items'] ?? [],
            'addresses' => $this->addressesPayload($source['addresses'] ?? []),
        ];

        $documentId = $this->saveDraft($tenantId, $payload);
        $this->appendHistory($tenantId, $documentId, 'document.converted', ['source_document_id' => $sourceDocumentId, 'source_type' => 'quote']);

        return $documentId;
    }

    public function createCreditNote(string $tenantId, int $sourceDocumentId, array $payload): int
    {
        $source = $this->getDocument($tenantId, $sourceDocumentId);
        if ($source === null) {
            throw new RuntimeException('document_not_found');
        }

        $lineItems = is_array($payload['line_items'] ?? null) && $payload['line_items'] !== []
            ? $payload['line_items']
            : ($source['line_items'] ?? []);

        $normalizedLineItems = array_map(static function (array $item): array {
            $quantity = abs((float) ($item['quantity'] ?? 1));
            $unitPrice = abs((float) ($item['unit_price'] ?? 0));
            return array_merge($item, [
                'quantity' => $quantity,
                'unit_price' => -1 * $unitPrice,
            ]);
        }, $lineItems);

        $creditPayload = [
            'document_type' => 'credit_note',
            'customer_id' => $source['customer_id'] !== null ? (int) $source['customer_id'] : null,
            'currency_code' => $source['currency_code'] ?? 'EUR',
            'exchange_rate' => $source['exchange_rate'] ?? 1,
            'discount_total' => 0,
            'shipping_total' => 0,
            'fees_total' => 0,
            'due_date' => $payload['due_date'] ?? null,
            'reference_document_id' => $sourceDocumentId,
            'line_items' => $normalizedLineItems,
            'addresses' => $this->addressesPayload($source['addresses'] ?? []),
        ];

        $documentId = $this->saveDraft($tenantId, $creditPayload);
        $this->appendHistory($tenantId, $documentId, 'document.credit_note_created', ['source_document_id' => $sourceDocumentId]);

        return $documentId;
    }

    public function history(string $tenantId, int $documentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT action_key, actor_id, metadata_json, created_at
             FROM billing_document_history
             WHERE tenant_id = :tenant_id AND document_id = :document_id
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'document_id' => $documentId]);

        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'action_key' => (string) ($row['action_key'] ?? ''),
                'actor_id' => (string) ($row['actor_id'] ?? 'system'),
                'metadata' => json_decode((string) ($row['metadata_json'] ?? '{}'), true) ?: [],
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $rows;
    }

    public function listCustomers(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_type, company_name, first_name, last_name, email, phone, vat_id, currency_code, created_at
             FROM billing_customers
             WHERE tenant_id = :tenant_id
             ORDER BY created_at DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll() ?: [];
    }

    public function saveCustomer(string $tenantId, array $payload, ?int $customerId = null): int
    {
        $type = ($payload['customer_type'] ?? 'company') === 'private' ? 'private' : 'company';

        if ($customerId === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO billing_customers
                    (tenant_id, customer_type, company_name, first_name, last_name, email, phone, vat_id, currency_code)
                 VALUES
                    (:tenant_id, :customer_type, :company_name, :first_name, :last_name, :email, :phone, :vat_id, :currency_code)'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'customer_type' => $type,
                'company_name' => $payload['company_name'] ?? null,
                'first_name' => $payload['first_name'] ?? null,
                'last_name' => $payload['last_name'] ?? null,
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'vat_id' => $payload['vat_id'] ?? null,
                'currency_code' => strtoupper((string) ($payload['currency_code'] ?? 'EUR')),
            ]);
            $customerId = (int) $this->pdo->lastInsertId();
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE billing_customers
                 SET customer_type = :customer_type,
                     company_name = :company_name,
                     first_name = :first_name,
                     last_name = :last_name,
                     email = :email,
                     phone = :phone,
                     vat_id = :vat_id,
                     currency_code = :currency_code,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE tenant_id = :tenant_id AND id = :id'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'id' => $customerId,
                'customer_type' => $type,
                'company_name' => $payload['company_name'] ?? null,
                'first_name' => $payload['first_name'] ?? null,
                'last_name' => $payload['last_name'] ?? null,
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'vat_id' => $payload['vat_id'] ?? null,
                'currency_code' => strtoupper((string) ($payload['currency_code'] ?? 'EUR')),
            ]);

            $this->pdo->prepare('DELETE FROM billing_customer_addresses WHERE tenant_id = :tenant_id AND customer_id = :customer_id')->execute([
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
            ]);
        }

        $addresses = is_array($payload['addresses'] ?? null) ? $payload['addresses'] : [];
        $contacts = is_array($payload['contacts'] ?? null) ? $payload['contacts'] : [];
        $this->storeCustomerAddresses($tenantId, $customerId, $addresses);
        $this->storeCustomerContacts($tenantId, $customerId, $contacts);

        return $customerId;
    }

    private function calculateTotals(array $lineItems, float $discountTotal, float $shippingTotal, float $feesTotal): array
    {
        $normalized = [];
        $subtotal = 0.0;
        $taxBuckets = [];

        foreach ($lineItems as $lineItem) {
            if (!is_array($lineItem)) {
                continue;
            }

            $quantity = $this->normalizeDecimal($lineItem['quantity'] ?? 1.0, 4);
            $unitPrice = $this->normalizeDecimal($lineItem['unit_price'] ?? 0.0);
            $lineDiscountPercent = $this->normalizeDecimal($lineItem['discount_percent'] ?? 0.0, 4);
            $lineDiscountAmount = $this->normalizeDecimal($lineItem['discount_amount'] ?? 0.0);
            $taxRate = $this->normalizeDecimal($lineItem['tax_rate'] ?? 0.0, 4);

            $lineNetRaw = $quantity * $unitPrice;
            $lineDiscountByPercent = $lineNetRaw * ($lineDiscountPercent / 100);
            $lineNet = $lineNetRaw - $lineDiscountByPercent - $lineDiscountAmount;
            $lineNet = $this->normalizeDecimal($lineNet);
            $subtotal += $lineNet;

            $normalized[] = [
                'name' => (string) ($lineItem['name'] ?? ''),
                'description' => (string) ($lineItem['description'] ?? ''),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => $lineDiscountPercent,
                'discount_amount' => $lineDiscountAmount,
                'tax_rate' => $taxRate,
                'line_net' => $lineNet,
            ];

            $taxKey = (string) $taxRate;
            if (!isset($taxBuckets[$taxKey])) {
                $taxBuckets[$taxKey] = ['net' => 0.0, 'rate' => $taxRate];
            }
            $taxBuckets[$taxKey]['net'] += $lineNet;
        }

        $subtotal = $this->normalizeDecimal($subtotal);
        $subtotalAfterGlobalDiscount = $this->normalizeDecimal($subtotal - $discountTotal);

        if ($subtotal !== 0.0 && $discountTotal !== 0.0) {
            foreach ($taxBuckets as $key => $bucket) {
                $share = $bucket['net'] / $subtotal;
                $taxBuckets[$key]['net'] = $this->normalizeDecimal($bucket['net'] - ($discountTotal * $share));
            }
        }

        $taxBreakdown = [];
        $taxTotal = 0.0;
        foreach ($taxBuckets as $bucket) {
            $net = $this->normalizeDecimal($bucket['net']);
            $tax = $this->normalizeDecimal($net * ($bucket['rate'] / 100));
            $gross = $this->normalizeDecimal($net + $tax);
            $taxTotal += $tax;
            $taxBreakdown[] = [
                'tax_rate' => $bucket['rate'],
                'net_amount' => $net,
                'tax_amount' => $tax,
                'gross_amount' => $gross,
            ];
        }

        $taxTotal = $this->normalizeDecimal($taxTotal);
        $grandTotal = $this->normalizeDecimal($subtotalAfterGlobalDiscount + $taxTotal + $shippingTotal + $feesTotal);

        return [
            'line_items' => $normalized,
            'subtotal_net' => $subtotal,
            'discount_total' => $discountTotal,
            'subtotal_after_discount' => $subtotalAfterGlobalDiscount,
            'shipping_total' => $shippingTotal,
            'fees_total' => $feesTotal,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
            'tax_breakdown' => $taxBreakdown,
        ];
    }

    private function storeLineItems(string $tenantId, int $documentId, array $lineItems): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO billing_line_items
                (tenant_id, document_id, position, name, description, quantity, unit_price, discount_percent, discount_amount, tax_rate, line_net)
             VALUES
                (:tenant_id, :document_id, :position, :name, :description, :quantity, :unit_price, :discount_percent, :discount_amount, :tax_rate, :line_net)'
        );

        $position = 1;
        foreach ($lineItems as $lineItem) {
            if (!is_array($lineItem)) {
                continue;
            }

            $quantity = $this->normalizeDecimal($lineItem['quantity'] ?? 1.0, 4);
            $unitPrice = $this->normalizeDecimal($lineItem['unit_price'] ?? 0.0);
            $lineDiscountPercent = $this->normalizeDecimal($lineItem['discount_percent'] ?? 0.0, 4);
            $lineDiscountAmount = $this->normalizeDecimal($lineItem['discount_amount'] ?? 0.0);
            $taxRate = $this->normalizeDecimal($lineItem['tax_rate'] ?? 0.0, 4);

            $lineNetRaw = $quantity * $unitPrice;
            $lineDiscountByPercent = $lineNetRaw * ($lineDiscountPercent / 100);
            $lineNet = $this->normalizeDecimal($lineNetRaw - $lineDiscountByPercent - $lineDiscountAmount);

            $stmt->execute([
                'tenant_id' => $tenantId,
                'document_id' => $documentId,
                'position' => $position,
                'name' => (string) ($lineItem['name'] ?? ''),
                'description' => (string) ($lineItem['description'] ?? ''),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => $lineDiscountPercent,
                'discount_amount' => $lineDiscountAmount,
                'tax_rate' => $taxRate,
                'line_net' => $lineNet,
            ]);
            $position++;
        }
    }

    private function storeTaxBreakdown(string $tenantId, int $documentId, array $taxBreakdown): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO billing_tax_breakdowns
                (tenant_id, document_id, tax_rate, net_amount, tax_amount, gross_amount)
             VALUES
                (:tenant_id, :document_id, :tax_rate, :net_amount, :tax_amount, :gross_amount)'
        );

        foreach ($taxBreakdown as $item) {
            if (!is_array($item)) {
                continue;
            }

            $stmt->execute([
                'tenant_id' => $tenantId,
                'document_id' => $documentId,
                'tax_rate' => $this->normalizeDecimal($item['tax_rate'] ?? 0.0, 4),
                'net_amount' => $this->normalizeDecimal($item['net_amount'] ?? 0.0),
                'tax_amount' => $this->normalizeDecimal($item['tax_amount'] ?? 0.0),
                'gross_amount' => $this->normalizeDecimal($item['gross_amount'] ?? 0.0),
            ]);
        }
    }

    private function storeAddresses(string $tenantId, int $documentId, mixed $addressesPayload, ?array $customer): void
    {
        $addresses = is_array($addressesPayload) ? $addressesPayload : [];
        if ($addresses === [] && $customer !== null) {
            $addresses = $this->customerDefaultAddresses($tenantId, (int) $customer['id']);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO billing_document_addresses
                (tenant_id, document_id, address_type, company_name, first_name, last_name, street, house_number, postal_code, city, country, email, phone)
             VALUES
                (:tenant_id, :document_id, :address_type, :company_name, :first_name, :last_name, :street, :house_number, :postal_code, :city, :country, :email, :phone)'
        );

        foreach ($addresses as $address) {
            if (!is_array($address)) {
                continue;
            }

            $stmt->execute([
                'tenant_id' => $tenantId,
                'document_id' => $documentId,
                'address_type' => (string) ($address['address_type'] ?? 'billing'),
                'company_name' => $address['company_name'] ?? null,
                'first_name' => $address['first_name'] ?? null,
                'last_name' => $address['last_name'] ?? null,
                'street' => $address['street'] ?? null,
                'house_number' => $address['house_number'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
                'city' => $address['city'] ?? null,
                'country' => $address['country'] ?? null,
                'email' => $address['email'] ?? null,
                'phone' => $address['phone'] ?? null,
            ]);
        }
    }

    private function reserveNumber(string $tenantId, string $series, int $year): int
    {
        $select = $this->pdo->prepare(
            'SELECT current_number FROM billing_number_counters WHERE tenant_id = :tenant_id AND series_key = :series_key AND year = :year FOR UPDATE'
        );
        $select->execute(['tenant_id' => $tenantId, 'series_key' => $series, 'year' => $year]);
        $row = $select->fetch();

        if (!is_array($row)) {
            $this->pdo->prepare(
                'INSERT INTO billing_number_counters (tenant_id, series_key, year, current_number)
                 VALUES (:tenant_id, :series_key, :year, 1)'
            )->execute(['tenant_id' => $tenantId, 'series_key' => $series, 'year' => $year]);
            return 1;
        }

        $next = ((int) $row['current_number']) + 1;
        $this->pdo->prepare(
            'UPDATE billing_number_counters
             SET current_number = :current_number, updated_at = CURRENT_TIMESTAMP
             WHERE tenant_id = :tenant_id AND series_key = :series_key AND year = :year'
        )->execute(['tenant_id' => $tenantId, 'series_key' => $series, 'year' => $year, 'current_number' => $next]);

        return $next;
    }

    private function assertMutable(string $tenantId, int $documentId): void
    {
        $stmt = $this->pdo->prepare('SELECT status FROM billing_documents WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $documentId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('document_not_found');
        }

        if ((string) ($row['status'] ?? '') !== 'draft') {
            throw new RuntimeException('document_not_mutable');
        }
    }

    private function getDocumentForUpdate(string $tenantId, int $documentId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM billing_documents WHERE tenant_id = :tenant_id AND id = :id FOR UPDATE');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $documentId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function appendHistory(string $tenantId, int $documentId, string $actionKey, array $metadata = []): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO billing_document_history (tenant_id, document_id, action_key, actor_id, metadata_json)
             VALUES (:tenant_id, :document_id, :action_key, :actor_id, :metadata_json)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'document_id' => $documentId,
            'action_key' => $actionKey,
            'actor_id' => 'system',
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    private function normalizeDocumentType(string $documentType): string
    {
        $value = strtolower(trim($documentType));
        $allowed = ['quote', 'order_confirmation', 'invoice', 'credit_note', 'cancellation'];

        if (!in_array($value, $allowed, true)) {
            throw new RuntimeException('invalid_document_type');
        }

        return $value;
    }

    private function normalizeDecimal(mixed $value, int $precision = 2): float
    {
        return round((float) $value, $precision);
    }

    private function findCustomer(string $tenantId, int $customerId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM billing_customers WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $customerId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function customerNameSnapshot(?array $customer): ?string
    {
        if ($customer === null) {
            return null;
        }

        $companyName = trim((string) ($customer['company_name'] ?? ''));
        if ($companyName !== '') {
            return $companyName;
        }

        return trim(((string) ($customer['first_name'] ?? '')) . ' ' . ((string) ($customer['last_name'] ?? '')));
    }

    private function customerDefaultAddresses(string $tenantId, int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT address_type, company_name, first_name, last_name, street, house_number, postal_code, city, country, email, phone
             FROM billing_customer_addresses
             WHERE tenant_id = :tenant_id AND customer_id = :customer_id AND is_default = 1'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'customer_id' => $customerId]);

        return $stmt->fetchAll() ?: [];
    }

    private function storeCustomerAddresses(string $tenantId, int $customerId, array $addresses): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO billing_customer_addresses
                (tenant_id, customer_id, address_type, company_name, first_name, last_name, street, house_number, postal_code, city, country, email, phone, is_default)
             VALUES
                (:tenant_id, :customer_id, :address_type, :company_name, :first_name, :last_name, :street, :house_number, :postal_code, :city, :country, :email, :phone, :is_default)'
        );

        foreach ($addresses as $address) {
            if (!is_array($address)) {
                continue;
            }

            $stmt->execute([
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'address_type' => (string) ($address['address_type'] ?? 'billing'),
                'company_name' => $address['company_name'] ?? null,
                'first_name' => $address['first_name'] ?? null,
                'last_name' => $address['last_name'] ?? null,
                'street' => $address['street'] ?? null,
                'house_number' => $address['house_number'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
                'city' => $address['city'] ?? null,
                'country' => $address['country'] ?? null,
                'email' => $address['email'] ?? null,
                'phone' => $address['phone'] ?? null,
                'is_default' => (int) ((bool) ($address['is_default'] ?? false)),
            ]);
        }
    }

    private function storeCustomerContacts(string $tenantId, int $customerId, array $contacts): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO billing_customer_contacts (tenant_id, customer_id, first_name, last_name, email, phone, role_label, is_primary)
             VALUES (:tenant_id, :customer_id, :first_name, :last_name, :email, :phone, :role_label, :is_primary)'
        );

        foreach ($contacts as $contact) {
            if (!is_array($contact)) {
                continue;
            }

            $stmt->execute([
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'first_name' => $contact['first_name'] ?? null,
                'last_name' => $contact['last_name'] ?? null,
                'email' => $contact['email'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'role_label' => $contact['role_label'] ?? null,
                'is_primary' => (int) ((bool) ($contact['is_primary'] ?? false)),
            ]);
        }
    }

    private function addressesPayload(array $addresses): array
    {
        return array_map(static fn (array $address): array => [
            'address_type' => $address['address_type'] ?? 'billing',
            'company_name' => $address['company_name'] ?? null,
            'first_name' => $address['first_name'] ?? null,
            'last_name' => $address['last_name'] ?? null,
            'street' => $address['street'] ?? null,
            'house_number' => $address['house_number'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'city' => $address['city'] ?? null,
            'country' => $address['country'] ?? null,
            'email' => $address['email'] ?? null,
            'phone' => $address['phone'] ?? null,
        ], $addresses);
    }
}
