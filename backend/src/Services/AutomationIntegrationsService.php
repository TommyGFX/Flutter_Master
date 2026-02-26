<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class AutomationIntegrationsService
{
    private const CRM_PROVIDERS = ['hubspot', 'pipedrive'];
    private const AUTOMATION_PROVIDERS = ['zapier', 'make'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listApiVersions(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT api_name, version, base_path, is_deprecated, sunset_at, idempotency_required, updated_at
             FROM automation_api_versions
             WHERE tenant_id = :tenant_id
             ORDER BY api_name ASC, version DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(static fn (array $row): array => [
            'api_name' => (string) ($row['api_name'] ?? ''),
            'version' => (string) ($row['version'] ?? ''),
            'base_path' => (string) ($row['base_path'] ?? ''),
            'is_deprecated' => (bool) ($row['is_deprecated'] ?? false),
            'sunset_at' => $row['sunset_at'] ?? null,
            'idempotency_required' => (bool) ($row['idempotency_required'] ?? false),
            'updated_at' => $row['updated_at'] ?? null,
        ], $stmt->fetchAll() ?: []);
    }

    public function registerApiVersion(string $tenantId, array $payload): array
    {
        $apiName = $this->normalizeKey($payload['api_name'] ?? null);
        $version = $this->normalizeVersion($payload['version'] ?? null);
        $basePath = $this->normalizeBasePath($payload['base_path'] ?? null);

        if ($apiName === '' || $version === '' || $basePath === '') {
            throw new RuntimeException('invalid_api_contract');
        }

        $isDeprecated = (bool) ($payload['is_deprecated'] ?? false);
        $idempotencyRequired = (bool) ($payload['idempotency_required'] ?? true);
        $sunsetAt = $this->nullableString($payload['sunset_at'] ?? null);

        $stmt = $this->pdo->prepare(
            'INSERT INTO automation_api_versions (tenant_id, api_name, version, base_path, is_deprecated, sunset_at, idempotency_required)
             VALUES (:tenant_id, :api_name, :version, :base_path, :is_deprecated, :sunset_at, :idempotency_required)
             ON DUPLICATE KEY UPDATE
                base_path = VALUES(base_path),
                is_deprecated = VALUES(is_deprecated),
                sunset_at = VALUES(sunset_at),
                idempotency_required = VALUES(idempotency_required),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'api_name' => $apiName,
            'version' => $version,
            'base_path' => $basePath,
            'is_deprecated' => $isDeprecated ? 1 : 0,
            'sunset_at' => $sunsetAt,
            'idempotency_required' => $idempotencyRequired ? 1 : 0,
        ]);

        return [
            'api_name' => $apiName,
            'version' => $version,
            'base_path' => $basePath,
            'is_deprecated' => $isDeprecated,
            'sunset_at' => $sunsetAt,
            'idempotency_required' => $idempotencyRequired,
        ];
    }

    public function claimIdempotencyKey(string $tenantId, array $payload): array
    {
        $idempotencyKey = $this->normalizeIdempotencyKey($payload['idempotency_key'] ?? null);
        $scope = $this->normalizeKey($payload['scope'] ?? 'global');
        $requestHash = hash('sha256', json_encode($payload['request_payload'] ?? [], JSON_THROW_ON_ERROR));

        if ($idempotencyKey === '' || $scope === '') {
            throw new RuntimeException('invalid_idempotency_request');
        }

        $stmt = $this->pdo->prepare(
            'SELECT request_hash, response_json, status_code, created_at
             FROM automation_idempotency_keys
             WHERE tenant_id = :tenant_id AND idempotency_key = :idempotency_key AND scope = :scope
             LIMIT 1'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'idempotency_key' => $idempotencyKey,
            'scope' => $scope,
        ]);
        $existing = $stmt->fetch();

        if (is_array($existing)) {
            if (($existing['request_hash'] ?? '') !== $requestHash) {
                throw new RuntimeException('idempotency_key_conflict');
            }

            return [
                'idempotency_key' => $idempotencyKey,
                'scope' => $scope,
                'is_replay' => true,
                'status_code' => (int) ($existing['status_code'] ?? 200),
                'response' => $this->decodeJsonObject($existing['response_json'] ?? null),
                'created_at' => $existing['created_at'] ?? null,
            ];
        }

        $responsePayload = is_array($payload['response_payload'] ?? null) ? $payload['response_payload'] : ['accepted' => true];
        $statusCode = (int) ($payload['status_code'] ?? 202);
        $statusCode = max(100, min(599, $statusCode));

        $insert = $this->pdo->prepare(
            'INSERT INTO automation_idempotency_keys (tenant_id, idempotency_key, scope, request_hash, response_json, status_code)
             VALUES (:tenant_id, :idempotency_key, :scope, :request_hash, :response_json, :status_code)'
        );
        $insert->execute([
            'tenant_id' => $tenantId,
            'idempotency_key' => $idempotencyKey,
            'scope' => $scope,
            'request_hash' => $requestHash,
            'response_json' => json_encode($responsePayload, JSON_THROW_ON_ERROR),
            'status_code' => $statusCode,
        ]);

        return [
            'idempotency_key' => $idempotencyKey,
            'scope' => $scope,
            'is_replay' => false,
            'status_code' => $statusCode,
            'response' => $responsePayload,
        ];
    }

    public function listCrmConnectors(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT provider, is_enabled, sync_mode, credentials_json, updated_at
             FROM automation_crm_connectors
             WHERE tenant_id = :tenant_id
             ORDER BY provider ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(function (array $row): array {
            $credentials = [];
            $decoded = json_decode((string) ($row['credentials_json'] ?? '[]'), true);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    if (is_string($key) && trim($key) !== '') {
                        $credentials[$key] = $value === null || $value === '' ? null : '***';
                    }
                }
            }

            return [
                'provider' => (string) ($row['provider'] ?? ''),
                'is_enabled' => (bool) ($row['is_enabled'] ?? false),
                'sync_mode' => (string) ($row['sync_mode'] ?? 'manual'),
                'credentials' => $credentials,
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, $stmt->fetchAll() ?: []);
    }

    public function upsertCrmConnector(string $tenantId, array $payload): array
    {
        $provider = strtolower(trim((string) ($payload['provider'] ?? '')));
        if (!in_array($provider, self::CRM_PROVIDERS, true)) {
            throw new RuntimeException('invalid_crm_provider');
        }

        $syncMode = strtolower(trim((string) ($payload['sync_mode'] ?? 'manual')));
        if (!in_array($syncMode, ['manual', 'scheduled', 'realtime'], true)) {
            throw new RuntimeException('invalid_sync_mode');
        }

        $credentials = is_array($payload['credentials'] ?? null) ? $payload['credentials'] : [];
        $isEnabled = (bool) ($payload['is_enabled'] ?? false);

        $stmt = $this->pdo->prepare(
            'INSERT INTO automation_crm_connectors (tenant_id, provider, credentials_json, sync_mode, is_enabled)
             VALUES (:tenant_id, :provider, :credentials_json, :sync_mode, :is_enabled)
             ON DUPLICATE KEY UPDATE
                credentials_json = VALUES(credentials_json),
                sync_mode = VALUES(sync_mode),
                is_enabled = VALUES(is_enabled),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'credentials_json' => json_encode($credentials, JSON_THROW_ON_ERROR),
            'sync_mode' => $syncMode,
            'is_enabled' => $isEnabled ? 1 : 0,
        ]);

        return [
            'provider' => $provider,
            'is_enabled' => $isEnabled,
            'sync_mode' => $syncMode,
            'credentials' => array_fill_keys(array_keys($credentials), '***'),
        ];
    }

    public function syncCrmEntity(string $tenantId, string $provider, array $payload): array
    {
        $provider = strtolower(trim($provider));
        if (!in_array($provider, self::CRM_PROVIDERS, true)) {
            throw new RuntimeException('invalid_crm_provider');
        }

        if (!$this->isCrmEnabled($tenantId, $provider)) {
            throw new RuntimeException('crm_connector_not_enabled');
        }

        $entityType = strtolower(trim((string) ($payload['entity_type'] ?? 'customer')));
        $entityId = trim((string) ($payload['entity_id'] ?? ''));
        $entityPayload = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];
        if ($entityId === '') {
            throw new RuntimeException('invalid_entity_id');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO automation_crm_sync_logs (tenant_id, provider, entity_type, entity_id, payload_json, sync_status)
             VALUES (:tenant_id, :provider, :entity_type, :entity_id, :payload_json, :sync_status)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload_json' => json_encode($entityPayload, JSON_THROW_ON_ERROR),
            'sync_status' => 'queued',
        ]);

        return [
            'provider' => $provider,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status' => 'queued',
        ];
    }

    public function listTimeEntries(string $tenantId, ?string $projectId): array
    {
        $params = ['tenant_id' => $tenantId];
        $whereProject = '';
        if ($projectId !== null && trim($projectId) !== '') {
            $whereProject = ' AND project_id = :project_id';
            $params['project_id'] = trim($projectId);
        }

        $stmt = $this->pdo->prepare(
            'SELECT entry_key, project_id, user_id, work_date, hours, hourly_rate, description, billable_status, created_at
             FROM automation_time_entries
             WHERE tenant_id = :tenant_id' . $whereProject . '
             ORDER BY work_date DESC, id DESC'
        );
        $stmt->execute($params);

        return array_map(static fn (array $row): array => [
            'entry_key' => (string) ($row['entry_key'] ?? ''),
            'project_id' => (string) ($row['project_id'] ?? ''),
            'user_id' => (string) ($row['user_id'] ?? ''),
            'work_date' => (string) ($row['work_date'] ?? ''),
            'hours' => (float) ($row['hours'] ?? 0),
            'hourly_rate' => (float) ($row['hourly_rate'] ?? 0),
            'description' => $row['description'] ?? null,
            'billable_status' => (string) ($row['billable_status'] ?? 'open'),
            'created_at' => $row['created_at'] ?? null,
        ], $stmt->fetchAll() ?: []);
    }

    public function upsertTimeEntry(string $tenantId, array $payload): array
    {
        $entryKey = $this->normalizeKey($payload['entry_key'] ?? null);
        $projectId = $this->normalizeKey($payload['project_id'] ?? null);
        $userId = $this->normalizeKey($payload['user_id'] ?? null);
        $workDate = $this->normalizeDate($payload['work_date'] ?? null);
        $description = $this->nullableString($payload['description'] ?? null);
        $hours = round((float) ($payload['hours'] ?? 0), 4);
        $hourlyRate = round((float) ($payload['hourly_rate'] ?? 0), 2);

        if ($entryKey === '' || $projectId === '' || $userId === '' || $workDate === null) {
            throw new RuntimeException('invalid_time_entry_reference');
        }
        if ($hours <= 0 || $hourlyRate < 0) {
            throw new RuntimeException('invalid_time_entry_values');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO automation_time_entries (tenant_id, entry_key, project_id, user_id, work_date, hours, hourly_rate, description, billable_status)
             VALUES (:tenant_id, :entry_key, :project_id, :user_id, :work_date, :hours, :hourly_rate, :description, :billable_status)
             ON DUPLICATE KEY UPDATE
                project_id = VALUES(project_id),
                user_id = VALUES(user_id),
                work_date = VALUES(work_date),
                hours = VALUES(hours),
                hourly_rate = VALUES(hourly_rate),
                description = VALUES(description),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'entry_key' => $entryKey,
            'project_id' => $projectId,
            'user_id' => $userId,
            'work_date' => $workDate,
            'hours' => $hours,
            'hourly_rate' => $hourlyRate,
            'description' => $description,
            'billable_status' => 'open',
        ]);

        return [
            'entry_key' => $entryKey,
            'project_id' => $projectId,
            'user_id' => $userId,
            'work_date' => $workDate,
            'hours' => $hours,
            'hourly_rate' => $hourlyRate,
            'description' => $description,
            'billable_status' => 'open',
        ];
    }

    public function invoiceTimeEntries(string $tenantId, array $payload): array
    {
        $projectId = $this->normalizeKey($payload['project_id'] ?? null);
        $customerId = (int) ($payload['customer_id'] ?? 0);
        if ($projectId === '' || $customerId <= 0) {
            throw new RuntimeException('invalid_invoice_request');
        }

        $entriesStmt = $this->pdo->prepare(
            "SELECT id, entry_key, work_date, hours, hourly_rate, description
             FROM automation_time_entries
             WHERE tenant_id = :tenant_id
               AND project_id = :project_id
               AND billable_status = 'open'
             ORDER BY work_date ASC, id ASC"
        );
        $entriesStmt->execute(['tenant_id' => $tenantId, 'project_id' => $projectId]);
        $entries = $entriesStmt->fetchAll() ?: [];
        if ($entries === []) {
            throw new RuntimeException('no_billable_time_entries');
        }

        $subtotal = 0.0;
        foreach ($entries as $entry) {
            $subtotal += (float) ($entry['hours'] ?? 0) * (float) ($entry['hourly_rate'] ?? 0);
        }
        $subtotal = round($subtotal, 2);
        $taxRate = round((float) ($payload['tax_rate'] ?? 19.0), 4);
        $taxTotal = round($subtotal * ($taxRate / 100), 2);
        $grandTotal = round($subtotal + $taxTotal, 2);

        $this->pdo->beginTransaction();
        try {
            $docStmt = $this->pdo->prepare(
                'INSERT INTO billing_documents
                    (tenant_id, plugin_key, document_type, status, customer_id, customer_name_snapshot, currency_code,
                     subtotal_net, discount_total, shipping_total, fees_total, tax_total, grand_total, totals_json)
                 VALUES
                    (:tenant_id, :plugin_key, :document_type, :status, :customer_id,
                     (SELECT COALESCE(company_name, CONCAT(COALESCE(first_name, ""), " ", COALESCE(last_name, "")))
                      FROM billing_customers WHERE id = :customer_id AND tenant_id = :tenant_id LIMIT 1),
                     :currency_code, :subtotal_net, 0, 0, 0, :tax_total, :grand_total, :totals_json)'
            );
            $docStmt->execute([
                'tenant_id' => $tenantId,
                'plugin_key' => 'automation_integrations',
                'document_type' => 'invoice',
                'status' => 'draft',
                'customer_id' => $customerId,
                'currency_code' => 'EUR',
                'subtotal_net' => $subtotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'totals_json' => json_encode([
                    'subtotal_net' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax_total' => $taxTotal,
                    'grand_total' => $grandTotal,
                    'source' => 'time_tracking',
                    'project_id' => $projectId,
                ], JSON_THROW_ON_ERROR),
            ]);
            $documentId = (int) $this->pdo->lastInsertId();

            $lineStmt = $this->pdo->prepare(
                'INSERT INTO billing_line_items
                    (tenant_id, document_id, position, name, description, quantity, unit_price, discount_percent, discount_amount, tax_rate, line_net)
                 VALUES
                    (:tenant_id, :document_id, :position, :name, :description, :quantity, :unit_price, 0, 0, :tax_rate, :line_net)'
            );

            $updateEntry = $this->pdo->prepare(
                "UPDATE automation_time_entries
                 SET billable_status = 'invoiced', invoice_document_id = :document_id, updated_at = CURRENT_TIMESTAMP
                 WHERE tenant_id = :tenant_id AND id = :entry_id"
            );

            $position = 1;
            foreach ($entries as $entry) {
                $hours = (float) ($entry['hours'] ?? 0);
                $rate = (float) ($entry['hourly_rate'] ?? 0);
                $lineNet = round($hours * $rate, 2);
                $description = trim((string) ($entry['description'] ?? ''));

                $lineStmt->execute([
                    'tenant_id' => $tenantId,
                    'document_id' => $documentId,
                    'position' => $position,
                    'name' => sprintf('Zeiterfassung %s', (string) ($entry['work_date'] ?? '')), 
                    'description' => $description === '' ? null : $description,
                    'quantity' => $hours,
                    'unit_price' => $rate,
                    'tax_rate' => $taxRate,
                    'line_net' => $lineNet,
                ]);

                $updateEntry->execute([
                    'tenant_id' => $tenantId,
                    'entry_id' => (int) ($entry['id'] ?? 0),
                    'document_id' => $documentId,
                ]);

                $position++;
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'document_id' => $documentId,
            'project_id' => $projectId,
            'entries_invoiced' => count($entries),
            'subtotal_net' => $subtotal,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
            'status' => 'draft',
        ];
    }

    public function listAutomationCatalog(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT provider, trigger_key, action_key, description, is_enabled, updated_at
             FROM automation_workflow_catalog
             WHERE tenant_id = :tenant_id
             ORDER BY provider ASC, trigger_key ASC, action_key ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        $rows = $stmt->fetchAll() ?: [];
        if ($rows === []) {
            return [
                'providers' => self::AUTOMATION_PROVIDERS,
                'workflows' => [],
            ];
        }

        return [
            'providers' => self::AUTOMATION_PROVIDERS,
            'workflows' => array_map(static fn (array $row): array => [
                'provider' => (string) ($row['provider'] ?? ''),
                'trigger_key' => (string) ($row['trigger_key'] ?? ''),
                'action_key' => (string) ($row['action_key'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'is_enabled' => (bool) ($row['is_enabled'] ?? false),
                'updated_at' => $row['updated_at'] ?? null,
            ], $rows),
        ];
    }

    public function enqueueAutomationRun(string $tenantId, array $payload): array
    {
        $provider = strtolower(trim((string) ($payload['provider'] ?? '')));
        $triggerKey = $this->normalizeKey($payload['trigger_key'] ?? null);
        $actionKey = $this->normalizeKey($payload['action_key'] ?? null);
        $inputPayload = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];

        if (!in_array($provider, self::AUTOMATION_PROVIDERS, true) || $triggerKey === '' || $actionKey === '') {
            throw new RuntimeException('invalid_automation_request');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO automation_workflow_runs (tenant_id, provider, trigger_key, action_key, payload_json, run_status)
             VALUES (:tenant_id, :provider, :trigger_key, :action_key, :payload_json, :run_status)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'trigger_key' => $triggerKey,
            'action_key' => $actionKey,
            'payload_json' => json_encode($inputPayload, JSON_THROW_ON_ERROR),
            'run_status' => 'queued',
        ]);

        return [
            'run_id' => (int) $this->pdo->lastInsertId(),
            'provider' => $provider,
            'trigger_key' => $triggerKey,
            'action_key' => $actionKey,
            'status' => 'queued',
        ];
    }

    public function previewImport(string $tenantId, array $payload): array
    {
        $dataset = strtolower(trim((string) ($payload['dataset'] ?? '')));
        if (!in_array($dataset, ['customers', 'products', 'historical_invoices'], true)) {
            throw new RuntimeException('invalid_import_dataset');
        }

        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
        $validRows = 0;
        $invalidRows = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $invalidRows++;
                continue;
            }

            $hasMinimalData = match ($dataset) {
                'customers' => trim((string) ($row['company_name'] ?? $row['email'] ?? '')) !== '',
                'products' => trim((string) ($row['name'] ?? $row['sku'] ?? '')) !== '',
                'historical_invoices' => trim((string) ($row['document_number'] ?? '')) !== '' && isset($row['grand_total']),
                default => false,
            };

            if ($hasMinimalData) {
                $validRows++;
            } else {
                $invalidRows++;
            }
        }

        return [
            'tenant_id' => $tenantId,
            'dataset' => $dataset,
            'total_rows' => count($rows),
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'can_import' => $validRows > 0,
        ];
    }

    public function executeImport(string $tenantId, array $payload): array
    {
        $preview = $this->previewImport($tenantId, $payload);
        if (!(bool) ($preview['can_import'] ?? false)) {
            throw new RuntimeException('no_valid_import_rows');
        }

        $dataset = (string) ($preview['dataset'] ?? '');
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];

        $imported = 0;
        $this->pdo->beginTransaction();
        try {
            if ($dataset === 'customers') {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO billing_customers (tenant_id, customer_type, company_name, first_name, last_name, email, phone, vat_id, currency_code)
                     VALUES (:tenant_id, :customer_type, :company_name, :first_name, :last_name, :email, :phone, :vat_id, :currency_code)'
                );

                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $companyName = $this->nullableString($row['company_name'] ?? null);
                    $email = $this->nullableString($row['email'] ?? null);
                    if ($companyName === null && $email === null) {
                        continue;
                    }

                    $stmt->execute([
                        'tenant_id' => $tenantId,
                        'customer_type' => $companyName !== null ? 'company' : 'private',
                        'company_name' => $companyName,
                        'first_name' => $this->nullableString($row['first_name'] ?? null),
                        'last_name' => $this->nullableString($row['last_name'] ?? null),
                        'email' => $email,
                        'phone' => $this->nullableString($row['phone'] ?? null),
                        'vat_id' => $this->nullableString($row['vat_id'] ?? null),
                        'currency_code' => strtoupper(trim((string) ($row['currency_code'] ?? 'EUR'))),
                    ]);
                    $imported++;
                }
            } elseif ($dataset === 'products') {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO automation_import_products (tenant_id, sku, name, description, unit_price, tax_rate)
                     VALUES (:tenant_id, :sku, :name, :description, :unit_price, :tax_rate)'
                );

                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $name = $this->nullableString($row['name'] ?? null);
                    if ($name === null) {
                        continue;
                    }

                    $stmt->execute([
                        'tenant_id' => $tenantId,
                        'sku' => $this->nullableString($row['sku'] ?? null),
                        'name' => $name,
                        'description' => $this->nullableString($row['description'] ?? null),
                        'unit_price' => round((float) ($row['unit_price'] ?? 0), 2),
                        'tax_rate' => round((float) ($row['tax_rate'] ?? 19), 4),
                    ]);
                    $imported++;
                }
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO automation_import_historical_invoices (tenant_id, source_id, document_number, customer_name, currency_code, grand_total, issued_on, due_on)
                     VALUES (:tenant_id, :source_id, :document_number, :customer_name, :currency_code, :grand_total, :issued_on, :due_on)'
                );

                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $documentNumber = $this->nullableString($row['document_number'] ?? null);
                    if ($documentNumber === null) {
                        continue;
                    }

                    $stmt->execute([
                        'tenant_id' => $tenantId,
                        'source_id' => $this->nullableString($row['source_id'] ?? null),
                        'document_number' => $documentNumber,
                        'customer_name' => $this->nullableString($row['customer_name'] ?? null),
                        'currency_code' => strtoupper(trim((string) ($row['currency_code'] ?? 'EUR'))),
                        'grand_total' => round((float) ($row['grand_total'] ?? 0), 2),
                        'issued_on' => $this->normalizeDate($row['issued_on'] ?? null),
                        'due_on' => $this->normalizeDate($row['due_on'] ?? null),
                    ]);
                    $imported++;
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'dataset' => $dataset,
            'imported_rows' => $imported,
            'skipped_rows' => max(0, count($rows) - $imported),
        ];
    }

    private function isCrmEnabled(string $tenantId, string $provider): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT is_enabled
             FROM automation_crm_connectors
             WHERE tenant_id = :tenant_id AND provider = :provider
             LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'provider' => $provider]);
        $row = $stmt->fetch();

        return is_array($row) && (bool) ($row['is_enabled'] ?? false);
    }

    private function decodeJsonObject(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeKey(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $normalized = strtolower(trim($value));
        return preg_match('/^[a-z0-9][a-z0-9_.:-]{1,126}$/', $normalized) ? $normalized : '';
    }

    private function normalizeVersion(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $normalized = strtolower(trim($value));
        return preg_match('/^v?[0-9]+(\.[0-9]+){0,2}$/', $normalized) ? $normalized : '';
    }

    private function normalizeBasePath(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $normalized = '/' . ltrim(trim($value), '/');
        return preg_match('#^/[a-zA-Z0-9/_-]{2,190}$#', $normalized) ? $normalized : '';
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $trimmed)) {
            return null;
        }

        return $trimmed;
    }

    private function normalizeIdempotencyKey(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $normalized = trim($value);
        return preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $normalized) ? $normalized : '';
    }
}
