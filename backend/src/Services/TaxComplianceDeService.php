<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class TaxComplianceDeService
{
    private const EINVOICE_FORMATS = ['xrechnung', 'zugferd'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly BillingCoreService $billingCore
    ) {
    }

    public function getConfig(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT business_name, tax_number, vat_id, small_business_enabled, default_tax_category, supply_date_required,
                    service_date_required, country_code, updated_at
             FROM tenant_tax_profiles
             WHERE tenant_id = :tenant_id
             LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return [
                'business_name' => null,
                'tax_number' => null,
                'vat_id' => null,
                'small_business_enabled' => false,
                'default_tax_category' => 'standard',
                'supply_date_required' => true,
                'service_date_required' => false,
                'country_code' => 'DE',
            ];
        }

        $row['small_business_enabled'] = (bool) ($row['small_business_enabled'] ?? false);
        $row['supply_date_required'] = (bool) ($row['supply_date_required'] ?? true);
        $row['service_date_required'] = (bool) ($row['service_date_required'] ?? false);

        return $row;
    }

    public function saveConfig(string $tenantId, array $payload): array
    {
        $defaultTaxCategory = strtolower(trim((string) ($payload['default_tax_category'] ?? 'standard')));
        if (!in_array($defaultTaxCategory, ['standard', 'reduced', 'zero', 'reverse_charge', 'intra_community'], true)) {
            throw new RuntimeException('invalid_default_tax_category');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_tax_profiles
                (tenant_id, business_name, tax_number, vat_id, small_business_enabled, default_tax_category,
                 supply_date_required, service_date_required, country_code)
             VALUES
                (:tenant_id, :business_name, :tax_number, :vat_id, :small_business_enabled, :default_tax_category,
                 :supply_date_required, :service_date_required, :country_code)
             ON DUPLICATE KEY UPDATE
                business_name = VALUES(business_name),
                tax_number = VALUES(tax_number),
                vat_id = VALUES(vat_id),
                small_business_enabled = VALUES(small_business_enabled),
                default_tax_category = VALUES(default_tax_category),
                supply_date_required = VALUES(supply_date_required),
                service_date_required = VALUES(service_date_required),
                country_code = VALUES(country_code),
                updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'business_name' => $this->nullableString($payload['business_name'] ?? null),
            'tax_number' => strtoupper((string) ($payload['tax_number'] ?? '')),
            'vat_id' => strtoupper((string) ($payload['vat_id'] ?? '')),
            'small_business_enabled' => !empty($payload['small_business_enabled']) ? 1 : 0,
            'default_tax_category' => $defaultTaxCategory,
            'supply_date_required' => !array_key_exists('supply_date_required', $payload) || !empty($payload['supply_date_required']) ? 1 : 0,
            'service_date_required' => !empty($payload['service_date_required']) ? 1 : 0,
            'country_code' => strtoupper(trim((string) ($payload['country_code'] ?? 'DE'))),
        ]);

        return $this->getConfig($tenantId);
    }

    public function preflightDocument(string $tenantId, int $documentId): array
    {
        $config = $this->getConfig($tenantId);
        $document = $this->billingCore->getDocument($tenantId, $documentId);
        if ($document === null) {
            throw new RuntimeException('document_not_found');
        }

        $errors = [];
        $warnings = [];

        $hasBusinessName = is_string($config['business_name'] ?? null) && trim((string) $config['business_name']) !== '';
        if (!$hasBusinessName) {
            $errors[] = 'missing_business_name';
        }

        if (($config['small_business_enabled'] ?? false) !== true) {
            $hasTaxNumber = is_string($config['tax_number'] ?? null) && trim((string) $config['tax_number']) !== '';
            $hasVatId = is_string($config['vat_id'] ?? null) && trim((string) $config['vat_id']) !== '';
            if (!$hasTaxNumber && !$hasVatId) {
                $errors[] = 'missing_tax_number_or_vat_id';
            }
        }

        if (($config['supply_date_required'] ?? false) === true && empty($document['due_date'])) {
            $warnings[] = 'missing_due_date_as_supply_proxy';
        }

        $lineItems = is_array($document['line_items'] ?? null) ? $document['line_items'] : [];
        if ($lineItems === []) {
            $errors[] = 'missing_line_items';
        }

        $taxResult = $this->evaluateTaxRules($config, $lineItems, $document);
        $errors = [...$errors, ...$taxResult['errors']];
        $warnings = [...$warnings, ...$taxResult['warnings']];

        return [
            'document_id' => $documentId,
            'valid' => $errors === [],
            'small_business_enabled' => (bool) ($config['small_business_enabled'] ?? false),
            'tax_categories' => $taxResult['categories'],
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    public function sealFinalizedDocument(string $tenantId, int $documentId): array
    {
        $document = $this->billingCore->getDocument($tenantId, $documentId);
        if ($document === null) {
            throw new RuntimeException('document_not_found');
        }

        if (($document['status'] ?? '') !== 'paid' && ($document['status'] ?? '') !== 'sent' && ($document['status'] ?? '') !== 'due' && ($document['status'] ?? '') !== 'overdue' && ($document['status'] ?? '') !== 'partially_paid') {
            throw new RuntimeException('document_not_finalized');
        }

        $preflight = $this->preflightDocument($tenantId, $documentId);
        if (($preflight['valid'] ?? false) !== true) {
            throw new RuntimeException('preflight_failed');
        }

        $payload = [
            'document_id' => $documentId,
            'document_number' => $document['document_number'] ?? null,
            'status' => $document['status'] ?? null,
            'grand_total' => $document['grand_total'] ?? null,
            'currency_code' => $document['currency_code'] ?? null,
            'tax_breakdown' => $document['tax_breakdown'] ?? [],
            'line_items' => $document['line_items'] ?? [],
            'totals' => $document['totals'] ?? [],
        ];

        $sealHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        $stmt = $this->pdo->prepare(
            'INSERT INTO billing_document_compliance
                (tenant_id, document_id, plugin_key, is_sealed, seal_hash, sealed_at, preflight_status, preflight_report_json)
             VALUES
                (:tenant_id, :document_id, :plugin_key, 1, :seal_hash, NOW(), :preflight_status, :preflight_report_json)
             ON DUPLICATE KEY UPDATE
                is_sealed = VALUES(is_sealed),
                seal_hash = VALUES(seal_hash),
                sealed_at = VALUES(sealed_at),
                preflight_status = VALUES(preflight_status),
                preflight_report_json = VALUES(preflight_report_json),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'document_id' => $documentId,
            'plugin_key' => 'tax_compliance_de',
            'seal_hash' => $sealHash,
            'preflight_status' => 'passed',
            'preflight_report_json' => json_encode($preflight, JSON_THROW_ON_ERROR),
        ]);

        return [
            'document_id' => $documentId,
            'is_sealed' => true,
            'seal_hash' => $sealHash,
            'sealed_at' => date('c'),
        ];
    }

    public function createCorrectionDocument(string $tenantId, int $sourceDocumentId, array $payload): array
    {
        $source = $this->billingCore->getDocument($tenantId, $sourceDocumentId);
        if ($source === null) {
            throw new RuntimeException('document_not_found');
        }

        if (($source['status'] ?? '') === 'draft') {
            throw new RuntimeException('correction_requires_finalized_document');
        }

        $reason = trim((string) ($payload['reason'] ?? 'Korrekturbeleg'));
        $newId = $this->billingCore->createCreditNote($tenantId, $sourceDocumentId, [
            'due_date' => $payload['due_date'] ?? null,
            'line_items' => $payload['line_items'] ?? null,
        ]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO billing_document_compliance
                (tenant_id, document_id, plugin_key, is_sealed, correction_of_document_id, correction_reason, preflight_status)
             VALUES
                (:tenant_id, :document_id, :plugin_key, 0, :correction_of_document_id, :correction_reason, :preflight_status)
             ON DUPLICATE KEY UPDATE
                correction_of_document_id = VALUES(correction_of_document_id),
                correction_reason = VALUES(correction_reason),
                preflight_status = VALUES(preflight_status),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'document_id' => $newId,
            'plugin_key' => 'tax_compliance_de',
            'correction_of_document_id' => $sourceDocumentId,
            'correction_reason' => $reason,
            'preflight_status' => 'pending',
        ]);

        return [
            'document_id' => $newId,
            'document_type' => 'credit_note',
            'correction_of_document_id' => $sourceDocumentId,
            'reason' => $reason,
        ];
    }

    public function exportEInvoice(string $tenantId, int $documentId, string $format): array
    {
        $normalizedFormat = strtolower(trim($format));
        if (!in_array($normalizedFormat, self::EINVOICE_FORMATS, true)) {
            throw new RuntimeException('invalid_einvoice_format');
        }

        $document = $this->billingCore->getDocument($tenantId, $documentId);
        if ($document === null) {
            throw new RuntimeException('document_not_found');
        }

        $payload = [
            'format' => $normalizedFormat,
            'document_id' => $documentId,
            'document_number' => $document['document_number'] ?? null,
            'currency' => $document['currency_code'] ?? 'EUR',
            'grand_total' => $document['grand_total'] ?? 0,
            'customer' => $document['customer_name_snapshot'] ?? null,
            'line_items' => $document['line_items'] ?? [],
            'tax_breakdown' => $document['tax_breakdown'] ?? [],
            'generated_at' => gmdate('c'),
        ];

        $xml = $this->buildSimpleEInvoiceXml($payload);

        $stmt = $this->pdo->prepare(
            'INSERT INTO billing_einvoice_exchange
                (tenant_id, document_id, exchange_direction, invoice_format, payload_json, xml_content, status)
             VALUES
                (:tenant_id, :document_id, :exchange_direction, :invoice_format, :payload_json, :xml_content, :status)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'document_id' => $documentId,
            'exchange_direction' => 'export',
            'invoice_format' => $normalizedFormat,
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'xml_content' => $xml,
            'status' => 'generated',
        ]);

        return [
            'document_id' => $documentId,
            'format' => $normalizedFormat,
            'mime' => 'application/xml',
            'filename' => sprintf('%s-%s.xml', $normalizedFormat, (string) ($document['document_number'] ?? $documentId)),
            'content_base64' => base64_encode($xml),
        ];
    }

    public function importEInvoice(string $tenantId, array $payload): array
    {
        $format = strtolower(trim((string) ($payload['format'] ?? 'xrechnung')));
        if (!in_array($format, self::EINVOICE_FORMATS, true)) {
            throw new RuntimeException('invalid_einvoice_format');
        }

        $xml = trim((string) ($payload['xml_content'] ?? ''));
        if ($xml === '') {
            throw new RuntimeException('xml_content_required');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO billing_einvoice_exchange
                (tenant_id, document_id, exchange_direction, invoice_format, payload_json, xml_content, status)
             VALUES
                (:tenant_id, NULL, :exchange_direction, :invoice_format, :payload_json, :xml_content, :status)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'exchange_direction' => 'import',
            'invoice_format' => $format,
            'payload_json' => json_encode(['source' => 'api_import'], JSON_THROW_ON_ERROR),
            'xml_content' => $xml,
            'status' => 'received',
        ]);

        return [
            'status' => 'received',
            'format' => $format,
            'exchange_id' => (int) $this->pdo->lastInsertId(),
        ];
    }

    private function evaluateTaxRules(array $config, array $lineItems, array $document): array
    {
        $categories = [];
        $errors = [];
        $warnings = [];

        foreach ($lineItems as $lineItem) {
            if (!is_array($lineItem)) {
                continue;
            }

            $taxRate = round((float) ($lineItem['tax_rate'] ?? 0.0), 4);
            if (($config['small_business_enabled'] ?? false) === true && $taxRate > 0) {
                $errors[] = 'small_business_requires_zero_vat';
            }

            $category = 'standard';
            if (abs($taxRate - 7.0) < 0.0001) {
                $category = 'reduced';
            } elseif ($taxRate <= 0.0) {
                $category = 'zero';
            } elseif (($document['customer_name_snapshot'] ?? '') !== null && str_starts_with(strtoupper((string) ($document['customer_name_snapshot'] ?? '')), 'EU-')) {
                $category = 'intra_community';
                $warnings[] = 'eu_customer_detected_verify_vat_id';
            }

            $categories[] = $category;
        }

        return [
            'categories' => array_values(array_unique($categories)),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function buildSimpleEInvoiceXml(array $payload): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<eInvoice>',
            sprintf('  <format>%s</format>', htmlspecialchars((string) ($payload['format'] ?? ''), ENT_QUOTES, 'UTF-8')),
            sprintf('  <documentNumber>%s</documentNumber>', htmlspecialchars((string) ($payload['document_number'] ?? ''), ENT_QUOTES, 'UTF-8')),
            sprintf('  <currency>%s</currency>', htmlspecialchars((string) ($payload['currency'] ?? 'EUR'), ENT_QUOTES, 'UTF-8')),
            sprintf('  <grandTotal>%.2f</grandTotal>', (float) ($payload['grand_total'] ?? 0.0)),
            '</eInvoice>',
        ];

        return implode("\n", $lines);
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
